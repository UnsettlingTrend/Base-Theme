<?php

declare(strict_types=1);

namespace Drupal\strava_api\Drush\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the Strava API module.
 */
final class StravaApiCommands extends DrushCommands {

  const AUTHORIZE = 'strava:authorize';
  const AUTH_URL  = 'strava:auth-url';
  const TOKEN_URL = 'https://www.strava.com/oauth/token';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ClientInterface $httpClient,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('http_client'),
    );
  }

  /**
   * Print the Strava OAuth authorization URL to begin the auth flow.
   */
  #[CLI\Command(name: self::AUTH_URL, aliases: ['strava-auth-url'])]
  #[CLI\Usage(name: 'drush strava:auth-url', description: 'Print the URL to open in a browser to authorize Strava access')]
  public function authUrl(): void {
    $config    = $this->configFactory->get('strava_api.settings');
    $client_id = trim((string) $config->get('client_id'));

    if ($client_id === '') {
      $this->logger()->error('No client_id configured. Set it at Admin > Config > Strava API.');
      return;
    }

    $url = 'https://www.strava.com/oauth/authorize?' . http_build_query([
      'client_id'     => $client_id,
      'redirect_uri'  => 'http://localhost',
      'response_type' => 'code',
      'approval_prompt' => 'force',
      'scope'         => 'read',
    ]);

    $this->io()->section('Step 1: Open this URL in your browser');
    $this->io()->writeln($url);
    $this->io()->section('Step 2');
    $this->io()->writeln('After authorizing, you will be redirected to a localhost URL that fails to load.');
    $this->io()->writeln('Copy the <info>code=...</info> value from that URL and run:');
    $this->io()->writeln('  <info>lando drush strava:authorize YOUR_CODE</info>');
  }

  /**
   * Exchange a Strava authorization code for tokens and save them.
   */
  #[CLI\Command(name: self::AUTHORIZE, aliases: ['strava-auth'])]
  #[CLI\Argument(name: 'code', description: 'The authorization code from the Strava OAuth redirect URL.')]
  #[CLI\Usage(name: 'drush strava:authorize abc123def456', description: 'Exchange the authorization code for access + refresh tokens')]
  public function authorize(string $code): void {
    $config        = $this->configFactory->get('strava_api.settings');
    $client_id     = trim((string) $config->get('client_id'));
    $client_secret = trim((string) $config->get('client_secret'));

    if ($client_id === '' || $client_secret === '') {
      $this->logger()->error('client_id and client_secret must be set at Admin > Config > Strava API before authorizing.');
      return;
    }

    $this->io()->text('Exchanging authorization code with Strava...');

    try {
      $response = $this->httpClient->request('POST', self::TOKEN_URL, [
        'form_params' => [
          'client_id'     => $client_id,
          'client_secret' => $client_secret,
          'code'          => $code,
          'grant_type'    => 'authorization_code',
        ],
        'http_errors' => FALSE,
        'timeout'     => 15,
      ]);

      $body = (string) $response->getBody();
      $data = json_decode($body, TRUE);

      if ($response->getStatusCode() !== 200 || empty($data['access_token'])) {
        $this->logger()->error('Strava returned HTTP {status}: {body}', [
          'status' => $response->getStatusCode(),
          'body'   => $body,
        ]);
        return;
      }

      $this->configFactory->getEditable('strava_api.settings')
        ->set('access_token',  $data['access_token'])
        ->set('refresh_token', $data['refresh_token'])
        ->set('token_expires', (int) $data['expires_at'])
        ->save();

      $this->io()->success('Strava tokens saved successfully.');
      $this->io()->definitionList(
        ['Access token expires' => date('Y-m-d H:i:s', (int) $data['expires_at'])],
        ['Athlete'              => $data['athlete']['username'] ?? $data['athlete']['id'] ?? 'unknown'],
      );
    }
    catch (\Throwable $e) {
      $this->logger()->error('Request failed: ' . $e->getMessage());
    }
  }

}
