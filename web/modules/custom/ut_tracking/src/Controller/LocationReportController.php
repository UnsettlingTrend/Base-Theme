<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ut_tracking\Entity\GpsDevice;
use Drupal\ut_tracking\Entity\GpsLocation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * REST-style controller for the mobile app's tracking-specific API surface:
 * verifying a username/password login (GET /api/ut-tracking/whoami) and
 * reporting a location (POST /api/ut-tracking/location). Signing in with
 * Google lives in ut_utilities\Controller\AppAuthController instead — it's
 * generic app authentication, not tracking-specific.
 *
 * /api/ut-tracking/location accepts a JSON body:
 *   {
 *     "latitude": 39.7392,
 *     "longitude": -104.9903,
 *     "accuracy": 12.5,
 *     "timestamp": 1754784000000,
 *     "device_id": "3fa5c95e-...",
 *     "device_name": "Pixel 8 Pro"
 *   }
 * "accuracy" and "device_name" are optional. "timestamp" is milliseconds
 * since the epoch (as returned by Android's Location.getTime()); omit it
 * to fall back to the server's receipt time. "device_id" is a stable
 * identifier the app generates and persists locally on first launch — not
 * a hardware serial — required so reports can be attributed to a device.
 */
class LocationReportController extends ControllerBase {

  /**
   * Lets the mobile app verify a username/password (or Bearer token —
   * see ut_utilities\Authentication\ApiTokenAuth) against this site, and
   * confirm the account can actually report locations, before saving
   * credentials on-device — without the side effect of creating a
   * gps_location/gps_device record the way POSTing to /location would.
   * Same auth/HTTPS requirements as that route; see ut_tracking.routing.yml.
   */
  public function whoAmI(): JsonResponse {
    return new JsonResponse([
      'uid' => (int) $this->currentUser()->id(),
      'name' => $this->currentUser()->getAccountName(),
    ]);
  }

  /**
   * Handles a location report POST.
   *
   * The owning user is always the caller authenticated via HTTP Basic Auth
   * (see ut_tracking.routing.yml) — never taken from the request body — so
   * one user can't report a location, or register a device, on another
   * user's behalf.
   */
  public function report(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data) || !isset($data['latitude'], $data['longitude'])) {
      throw new BadRequestHttpException('Missing required fields: latitude, longitude.');
    }
    if (empty($data['device_id']) || !is_string($data['device_id'])) {
      throw new BadRequestHttpException('Missing required field: device_id.');
    }

    $latitude = (float) $data['latitude'];
    $longitude = (float) $data['longitude'];
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
      throw new BadRequestHttpException('latitude/longitude out of range.');
    }

    $recorded = isset($data['timestamp'])
      ? (int) round(((float) $data['timestamp']) / 1000)
      : \Drupal::time()->getRequestTime();

    $device = $this->getOrCreateDevice(
      $data['device_id'],
      isset($data['device_name']) ? (string) $data['device_name'] : NULL,
      $recorded,
    );

    $location = GpsLocation::create([
      'uid' => $this->currentUser()->id(),
      'device' => $device->id(),
      'latitude' => $latitude,
      'longitude' => $longitude,
      'accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : NULL,
      'recorded' => $recorded,
    ]);
    $location->save();

    return new JsonResponse([
      'status' => 'ok',
      'id' => $location->id(),
      'device_id' => $device->id(),
    ], 201);
  }

  /**
   * Loads the caller's GpsDevice matching $device_identifier, or registers
   * a new one on first sight. Scoped to the current user — two different
   * users sending the same device_id (e.g. a bug, or a shared device) get
   * separate GpsDevice records, one per owner.
   */
  private function getOrCreateDevice(string $device_identifier, ?string $device_name, int $seen_at): GpsDevice {
    $storage = $this->entityTypeManager()->getStorage('gps_device');
    $uid = $this->currentUser()->id();

    $existing = $storage->loadByProperties([
      'uid' => $uid,
      'device_identifier' => $device_identifier,
    ]);
    /** @var \Drupal\ut_tracking\Entity\GpsDevice|null $device */
    $device = $existing ? reset($existing) : NULL;

    if (!$device) {
      $device = GpsDevice::create([
        'uid' => $uid,
        'device_identifier' => $device_identifier,
        'device_name' => $device_name,
        'last_seen' => $seen_at,
      ]);
      $device->save();
      return $device;
    }

    // Keep the latest name (a device can be renamed/updated) and last_seen
    // current on every report — this is the only field that changes on an
    // otherwise-immutable device record.
    if ($device_name !== NULL && $device->get('device_name')->value !== $device_name) {
      $device->set('device_name', $device_name);
    }
    $device->set('last_seen', $seen_at);
    $device->save();

    return $device;
  }

}
