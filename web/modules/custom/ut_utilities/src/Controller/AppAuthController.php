<?php

declare(strict_types=1);

namespace Drupal\ut_utilities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\ut_utilities\GoogleIdTokenVerifier;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Authentication surface for the mobile app: signing in with Google
 * (POST /api/app/google-login), issuing a Bearer token the app then sends
 * on every subsequent request to any app API endpoint (see
 * Authentication\ApiTokenAuth) in place of a Drupal password, since Google
 * sign-in never gives the app one.
 */
class AppAuthController extends ControllerBase {

  public function __construct(
    private readonly GoogleIdTokenVerifier $googleIdTokenVerifier,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('ut_utilities.google_id_token_verifier'));
  }

  /**
   * Verifies a Google ID token (obtained on-device via Android's
   * Credential Manager Sign-In-With-Google) and issues a Bearer token
   * the app uses for all subsequent requests in place of a Drupal
   * password — Google sign-in never gives the app one. Auto-registers a
   * new Drupal account on first sign-in from an unrecognized Google
   * account, mirroring this site's own "Login with Google" behavior on
   * the website (social_auth.settings: user_allowed = register).
   *
   * POST /api/app/google-login, JSON body: {"id_token": "..."}.
   */
  public function googleLogin(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data) || empty($data['id_token']) || !is_string($data['id_token'])) {
      throw new BadRequestHttpException('Missing required field: id_token.');
    }

    // Reusing social_auth_google's own configured Client ID as the
    // expected token audience — not duplicating it into a second config
    // value that could drift out of sync with the website's own Google
    // sign-in, and it's already the correct kind (a "Web application"
    // type client, which is what Android's Credential Manager API wants
    // as its serverClientId/audience too, per Google's own guidance).
    $client_id = $this->config('social_auth_google.settings')->get('client_id');
    if (!$client_id) {
      throw new HttpException(503, 'Google sign-in is not configured on this site.');
    }

    $payload = $this->googleIdTokenVerifier->verify($data['id_token'], $client_id);
    if (!$payload || empty($payload->sub) || empty($payload->email)) {
      throw new AccessDeniedHttpException('Invalid or expired Google sign-in.');
    }

    $user = $this->findOrCreateUserForGoogleAccount($payload);

    $token = bin2hex(random_bytes(32));
    \Drupal::database()->insert('ut_utilities_api_token')
      ->fields([
        'uid' => $user->id(),
        'token_hash' => hash('sha256', $token),
        'created' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    return new JsonResponse([
      'uid' => (int) $user->id(),
      'name' => $user->getAccountName(),
      'token' => $token,
    ]);
  }

  /**
   * Resolves the Drupal user for a verified Google ID token payload:
   * the account already linked via the same "social_auth" entity the
   * website's own Login-with-Google uses, an existing account matching
   * the Google account's email (linking it rather than creating a
   * duplicate), or — if neither exists — a brand new auto-registered
   * account.
   */
  private function findOrCreateUserForGoogleAccount(object $payload): UserInterface {
    $social_auth_storage = $this->entityTypeManager()->getStorage('social_auth');
    $existing_links = $social_auth_storage->loadByProperties([
      'plugin_id' => 'social_auth_google',
      'provider_user_id' => $payload->sub,
    ]);

    if ($existing_links) {
      $link = reset($existing_links);
      $user = User::load($link->get('user_id')->target_id);
      if ($user) {
        return $user;
      }
    }

    $user_storage = $this->entityTypeManager()->getStorage('user');
    $by_email = $user_storage->loadByProperties(['mail' => $payload->email]);
    /** @var \Drupal\user\UserInterface|null $user */
    $user = $by_email ? reset($by_email) : NULL;

    if (!$user) {
      $user = User::create([
        'name' => $this->generateUniqueUsername($payload->email),
        'mail' => $payload->email,
        'status' => 1,
      ]);
      $user->save();
    }

    $social_auth_storage->create([
      'user_id' => $user->id(),
      'plugin_id' => 'social_auth_google',
      'provider_user_id' => $payload->sub,
    ])->save();

    return $user;
  }

  /**
   * A username derived from the Google account's email local-part,
   * disambiguated with a numeric suffix if it's already taken.
   */
  private function generateUniqueUsername(string $email): string {
    $base = preg_replace('/[^a-zA-Z0-9_.\-]/', '', explode('@', $email)[0]) ?: 'user';
    $storage = $this->entityTypeManager()->getStorage('user');

    $candidate = $base;
    $suffix = 1;
    while ($storage->loadByProperties(['name' => $candidate])) {
      $suffix++;
      $candidate = $base . $suffix;
    }

    return $candidate;
  }

}
