<?php

declare(strict_types=1);

namespace Drupal\ut_utilities;

use Drupal\Core\Cache\CacheBackendInterface;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;

/**
 * Verifies a Google-issued ID token (JWT) server-side: signature, issuer,
 * audience, and expiry — the standard way a backend confirms a token a
 * native app got from Google Sign-In actually came from Google and was
 * issued for this app, without the app or server ever seeing the user's
 * Google password.
 */
class GoogleIdTokenVerifier {

  private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
  private const CACHE_ID = 'ut_utilities:google_jwks';
  private const VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * @return object|null
   *   The decoded token payload (sub, email, name, picture, ...) if the
   *   token's signature, issuer, and audience all check out and it
   *   hasn't expired. NULL for any failure — malformed, expired, wrong
   *   audience, wrong issuer, bad signature — deliberately without
   *   distinguishing which, since the caller only needs "valid or not".
   */
  public function verify(string $id_token, string $expected_audience): ?object {
    try {
      $keys = JWK::parseKeySet($this->getGoogleJwks());
      $payload = JWT::decode($id_token, $keys);
    }
    catch (\Throwable $e) {
      return NULL;
    }

    if (($payload->aud ?? NULL) !== $expected_audience) {
      return NULL;
    }
    if (!in_array($payload->iss ?? NULL, self::VALID_ISSUERS, TRUE)) {
      return NULL;
    }

    return $payload;
  }

  /**
   * Google's signing keys (JWKS), cached — these rotate infrequently and
   * Google's own response headers recommend caching them rather than
   * fetching fresh on every verification.
   */
  private function getGoogleJwks(): array {
    if ($cached = $this->cache->get(self::CACHE_ID)) {
      return $cached->data;
    }

    $response = $this->httpClient->request('GET', self::JWKS_URL);
    $keys = json_decode((string) $response->getBody(), TRUE);

    $this->cache->set(self::CACHE_ID, $keys, \Drupal::time()->getRequestTime() + 3600);

    return $keys;
  }

}
