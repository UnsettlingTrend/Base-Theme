<?php
declare(strict_types=1);
namespace Drupal\strava_api;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
/**
 * Lightweight client for reading Strava route payloads.
 *
 * Handles OAuth token refresh automatically: proactively refreshes when the
 * stored access token is within 5 minutes of expiry, and retries once on a
 * 401 in case the token expired between requests.
 */
class StravaRouteClient {

  private const API_BASE = 'https://www.strava.com/api/v3';
  private const TOKEN_URL = 'https://www.strava.com/oauth/token';
  private const REFRESH_BUFFER = 300;

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Fetches route details from Strava, refreshing the token if needed.
   */
  public function fetchRoute(string $routeId): ?array {
    if (!ctype_digit($routeId)) {
      return NULL;
    }

    $token = $this->getValidToken();
    if ($token === NULL) {
      return NULL;
    }

    try {
      $response = $this->httpClient->request('GET', self::API_BASE . '/routes/' . $routeId, [
        'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
        'http_errors' => FALSE,
        'timeout' => 15,
      ]);

      // On 401, attempt one token refresh and retry.
      if ($response->getStatusCode() === 401) {
        $token = $this->refreshToken();
        if ($token === NULL) {
          $this->logger->warning('Unable to fetch Strava route @id: token refresh failed.', ['@id' => $routeId]);
          return NULL;
        }
        $response = $this->httpClient->request('GET', self::API_BASE . '/routes/' . $routeId, [
          'headers' => ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'],
          'http_errors' => FALSE,
          'timeout' => 15,
        ]);
      }

      if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
        $this->logger->warning('Unable to fetch Strava route @id: Strava API returned HTTP @status.', [
          '@id' => $routeId,
          '@status' => $response->getStatusCode(),
        ]);
        return NULL;
      }

      $payload = json_decode((string) $response->getBody(), TRUE);
      return is_array($payload) ? $payload : NULL;
    }
    catch (\Throwable $e) {
      $this->logger->warning('Unable to fetch Strava route @id: @message', [
        '@id' => $routeId,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Returns a valid access token, refreshing proactively if near expiry.
   */
  private function getValidToken(): ?string {
    $config  = $this->configFactory->get('strava_api.settings');
    $token   = trim((string) $config->get('access_token'));
    $expires = (int) $config->get('token_expires');

    if ($token === '') {
      $this->logger->warning('Strava API: no access token configured.');
      return NULL;
    }

    // Proactively refresh if the token expires within the buffer window.
    if ($expires > 0 && time() >= ($expires - self::REFRESH_BUFFER)) {
      return $this->refreshToken() ?? $token;
    }

    return $token;
  }

  /**
   * Exchanges the refresh token for a new access token and persists it.
   *
   * @return string|null The new access token, or NULL on failure.
   */
  private function refreshToken(): ?string {
    $config        = $this->configFactory->get('strava_api.settings');
    $client_id     = trim((string) $config->get('client_id'));
    $client_secret = trim((string) $config->get('client_secret'));
    $refresh_token = trim((string) $config->get('refresh_token'));

    if ($client_id === '' || $client_secret === '' || $refresh_token === '') {
      $this->logger->warning('Strava API: cannot refresh token — client_id, client_secret, or refresh_token not configured.');
      return NULL;
    }

    try {
      $response = $this->httpClient->request('POST', self::TOKEN_URL, [
        'form_params' => [
          'client_id'     => $client_id,
          'client_secret' => $client_secret,
          'refresh_token' => $refresh_token,
          'grant_type'    => 'refresh_token',
        ],
        'http_errors' => FALSE,
        'timeout'     => 15,
      ]);

      if ($response->getStatusCode() !== 200) {
        $body = (string) $response->getBody();
        $this->logger->warning('Strava token refresh failed with HTTP @status: @body', [
          '@status' => $response->getStatusCode(),
          '@body'   => $body,
        ]);
        return NULL;
      }

      $data = json_decode((string) $response->getBody(), TRUE);
      if (empty($data['access_token'])) {
        $this->logger->warning('Strava token refresh returned an unexpected response.');
        return NULL;
      }

      $this->configFactory->getEditable('strava_api.settings')
        ->set('access_token',  $data['access_token'])
        ->set('refresh_token', $data['refresh_token'] ?? $refresh_token)
        ->set('token_expires', (int) ($data['expires_at'] ?? 0))
        ->save();

      $this->logger->info('Strava access token refreshed successfully.');
      return $data['access_token'];
    }
    catch (\Throwable $e) {
      $this->logger->warning('Strava token refresh threw an exception: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }
  }

}
