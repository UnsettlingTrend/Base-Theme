<?php
declare(strict_types=1);
namespace Drupal\strava_api;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
/**
 * Lightweight client for reading Strava route payloads.
 */
class StravaRouteClient {
  /**
   * The Strava API base URL.
   */
  private const API_BASE = 'https://www.strava.com/api/v3';
  /**
   * Creates a Strava route client service.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {
  }
  /**
   * Fetches route details from Strava.
   */
  public function fetchRoute(string $routeId): ?array {
    if (!ctype_digit($routeId)) {
      return NULL;
    }
    $token = trim((string) $this->configFactory->get('strava_api.settings')->get('access_token'));
    if ($token === '') {
      $this->logger->warning('Unable to fetch Strava route @id: no access token configured.', ['@id' => $routeId]);
      return NULL;
    }
    try {
      $response = $this->httpClient->request('GET', self::API_BASE . '/routes/' . $routeId, [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Accept' => 'application/json',
        ],
        'http_errors' => FALSE,
        'timeout' => 15,
      ]);
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
}
