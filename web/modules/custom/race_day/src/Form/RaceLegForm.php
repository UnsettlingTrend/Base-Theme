<?php

declare(strict_types=1);

namespace Drupal\race_day\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Race Leg add/edit forms.
 */
class RaceLegForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $this->populateFromStravaPayload();
    $result = parent::save($form, $form_state);
    $entity = $this->getEntity();

    $message = $result === SAVED_NEW
      ? $this->t('Leg %label has been created.', ['%label' => $entity->label()])
      : $this->t('Leg %label has been updated.', ['%label' => $entity->label()]);
    $this->messenger()->addStatus($message);

    $form_state->setRedirectUrl($entity->get('race_id')->entity?->toUrl('canonical')
      ?? $entity->toUrl('edit-form'));
    return $result;
  }

  /**
   * Copies Strava route payload values into fields on the embedded paragraph.
   */
  private function populateFromStravaPayload(): void {
    $entity = $this->getEntity();

    if ($entity->get('route')->isEmpty()) {
      return;
    }

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $entity->get('route')->entity;
    if (!$paragraph || !$paragraph->hasField('field_strava_route_id')) {
      return;
    }

    $route_id = trim((string) ($paragraph->get('field_strava_route_id')->value ?? ''));
    if (!ctype_digit($route_id)) {
      return;
    }

    $raw = $paragraph->hasField('field_route_data')
      ? trim((string) ($paragraph->get('field_route_data')->value ?? ''))
      : '';
    $payload = $this->decodePayload($raw);

    $needs_refresh = $payload === NULL
      || ((string) ($payload['id'] ?? '')) !== $route_id
      || empty($payload['map']['polyline'])
      || empty($payload['map']['summary_polyline']);

    if ($needs_refresh) {
      $fetched = $this->fetchRoutePayload($route_id);
      if (is_array($fetched)) {
        $payload = $fetched;
        $encoded = json_encode($fetched, JSON_UNESCAPED_SLASHES);
        if (is_string($encoded) && $paragraph->hasField('field_route_data')) {
          $paragraph->set('field_route_data', $encoded);
        }
      }
    }

    if (!is_array($payload)) {
      return;
    }

    $map = isset($payload['map']) && is_array($payload['map']) ? $payload['map'] : [];
    $polyline = (string) ($map['polyline'] ?? '');
    $summary_polyline = (string) ($map['summary_polyline'] ?? '');

    if ($polyline !== '' && $paragraph->hasField('field_strava_polyline')) {
      $paragraph->set('field_strava_polyline', $polyline);
    }
    if ($summary_polyline !== '' && $paragraph->hasField('field_strava_summary_polyline')) {
      $paragraph->set('field_strava_summary_polyline', $summary_polyline);
    }

    if (isset($payload['distance']) && is_numeric($payload['distance']) && $paragraph->hasField('field_distance')) {
      $miles = round(((float) $payload['distance']) * 0.000621371, 2);
      $paragraph->set('field_distance', $miles);
    }

    if (isset($payload['elevation_gain']) && is_numeric($payload['elevation_gain'])
      && $paragraph->hasField('field_elevation_gain')) {
      $feet = (int) round(((float) $payload['elevation_gain']) * 3.28084);
      $paragraph->set('field_elevation_gain', max(0, $feet));
    }

    if ($needs_refresh && $paragraph->hasField('field_elev_high') && $paragraph->hasField('field_elev_low')) {
      $streams = $this->fetchRouteStreams($route_id);
      $bounds = $streams !== NULL ? $this->extractElevationBounds($streams) : NULL;
      if ($bounds !== NULL) {
        $paragraph->set('field_elev_high', $bounds['high']);
        $paragraph->set('field_elev_low', $bounds['low']);
      }
    }

    $start = $this->extractLatLng(
      $payload['start_latlng']
      ?? [$payload['start_latitude'] ?? NULL, $payload['start_longitude'] ?? NULL]
    );
    $end = $this->extractLatLng(
      $payload['end_latlng']
      ?? [$payload['end_latitude'] ?? NULL, $payload['end_longitude'] ?? NULL]
    );

    // The Routes API does not return start/end lat-lng as discrete fields.
    // Decode the full polyline and use its first/last points as a fallback.
    if ($start === NULL || $end === NULL) {
      $points = $this->decodePolyline($polyline !== '' ? $polyline : $summary_polyline);
      if (count($points) >= 2) {
        $start ??= $points[0];
        $end ??= $points[count($points) - 1];
      }
    }

    if ($start !== NULL) {
      if ($paragraph->hasField('field_start_lat')) {
        $paragraph->set('field_start_lat', round($start[0], 7));
      }
      if ($paragraph->hasField('field_start_lng')) {
        $paragraph->set('field_start_lng', round($start[1], 7));
      }
    }

    if ($end !== NULL) {
      if ($paragraph->hasField('field_end_lat')) {
        $paragraph->set('field_end_lat', round($end[0], 7));
      }
      if ($paragraph->hasField('field_end_lng')) {
        $paragraph->set('field_end_lng', round($end[1], 7));
      }
    }

    // Mark the paragraph dirty so entity_reference_revisions saves it.
    if (method_exists($paragraph, 'setNeedsSave')) {
      $paragraph->setNeedsSave(TRUE);
    }
  }

  /**
   * Fetches the current Strava route payload using the configured API token.
   */
  private function fetchRoutePayload(string $route_id): ?array {
    if (!\Drupal::hasService('strava_api.route_client')) {
      return NULL;
    }

    try {
      $payload = \Drupal::service('strava_api.route_client')->fetchRoute($route_id);
      return is_array($payload) ? $payload : NULL;
    }
    catch (\Throwable $e) {
      \Drupal::logger('race_day')->warning('Failed to fetch Strava route @id: @message', [
        '@id' => $route_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Fetches the current Strava route streams using the configured API token.
   */
  private function fetchRouteStreams(string $route_id): ?array {
    if (!\Drupal::hasService('strava_api.route_client')) {
      return NULL;
    }

    try {
      $streams = \Drupal::service('strava_api.route_client')->fetchRouteStreams($route_id);
      return is_array($streams) ? $streams : NULL;
    }
    catch (\Throwable $e) {
      \Drupal::logger('race_day')->warning('Failed to fetch Strava route @id streams: @message', [
        '@id' => $route_id,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Returns the highest/lowest elevation (in feet) from a streams response.
   *
   * @param array $streams
   *   The decoded response from GET /routes/{id}/streams: a list of stream
   *   objects, each with a "type" (e.g. "altitude") and a "data" array.
   *
   * @return array{high: int, low: int}|null
   *   The route's highest/lowest elevation in feet, or NULL when the streams
   *   response has no usable altitude data.
   */
  private function extractElevationBounds(array $streams): ?array {
    $altitude = NULL;
    foreach ($streams as $stream) {
      if (is_array($stream) && ($stream['type'] ?? NULL) === 'altitude' && is_array($stream['data'] ?? NULL)) {
        $altitude = $stream['data'];
        break;
      }
    }

    if (empty($altitude)) {
      return NULL;
    }

    $meters = array_filter($altitude, 'is_numeric');
    if (empty($meters)) {
      return NULL;
    }

    return [
      'high' => (int) round(max($meters) * 3.28084),
      'low' => (int) round(min($meters) * 3.28084),
    ];
  }

  /**
   * Decodes stored route_data JSON and unwraps nested payloads when needed.
   */
  private function decodePayload(string $raw): ?array {
    if ($raw === '') {
      return NULL;
    }

    $payload = json_decode($raw, TRUE);
    if (!is_array($payload)) {
      return NULL;
    }

    if (isset($payload['route']) && is_array($payload['route'])) {
      return $payload['route'];
    }

    return $payload;
  }

  /**
   * Returns [lat, lng] when a Strava coordinate array is valid.
   *
   * @return array{0: float, 1: float}|null
   *   A normalized [lat, lng] pair, or NULL when unavailable.
   */
  private function extractLatLng(mixed $value): ?array {
    if (!is_array($value) || !isset($value[0], $value[1])) {
      return NULL;
    }
    if (!is_numeric($value[0]) || !is_numeric($value[1])) {
      return NULL;
    }

    return [(float) $value[0], (float) $value[1]];
  }

  /**
   * Decodes a Google-encoded polyline string into an array of [lat, lng] pairs.
   *
   * @return array<int, array{0: float, 1: float}>
   */
  private function decodePolyline(string $encoded): array {
    $points = [];
    $index = 0;
    $len = strlen($encoded);
    $lat = 0;
    $lng = 0;

    while ($index < $len) {
      foreach ([&$lat, &$lng] as &$coord) {
        $result = 0;
        $shift = 0;
        do {
          if ($index >= $len) {
            return $points;
          }
          $b = ord($encoded[$index++]) - 63;
          $result |= ($b & 0x1F) << $shift;
          $shift += 5;
        } while ($b >= 0x20);
        $coord += ($result & 1) ? ~($result >> 1) : ($result >> 1);
      }
      unset($coord);
      $points[] = [$lat / 1e5, $lng / 1e5];
    }

    return $points;
  }

}
