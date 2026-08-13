<?php

declare(strict_types=1);

namespace Drupal\ut_utilities\Authentication;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Authenticates requests bearing "Authorization: Bearer <token>", where
 * <token> was issued by AppAuthController::googleLogin() — the credential
 * the mobile app uses in place of a Drupal password after signing in with
 * Google, since Google sign-in never gives the app one to send as Basic
 * Auth.
 */
class ApiTokenAuth implements AuthenticationProviderInterface {

  public function __construct(private readonly Connection $database) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): bool {
    return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request): ?AccountInterface {
    $token = substr((string) $request->headers->get('Authorization'), 7);
    if ($token === '') {
      return NULL;
    }

    $uid = $this->database->select('ut_utilities_api_token', 't')
      ->fields('t', ['uid'])
      ->condition('token_hash', hash('sha256', $token))
      ->execute()
      ->fetchField();

    if (!$uid) {
      return NULL;
    }

    $user = User::load($uid);
    return ($user && $user->isActive()) ? $user : NULL;
  }

}
