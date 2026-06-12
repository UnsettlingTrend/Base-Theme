<?php

declare(strict_types=1);

namespace Drupal\race_day\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * REST-style controller for runner GPS location updates.
 *
 * Accepts POST requests to /api/race-day/location with JSON body:
 *   {
 *     "assignment_id": 123,
 *     "lat": 39.7392,
 *     "lng": -104.9903,
 *     "speed": 6.5
 *   }
 */
class LocationController extends ControllerBase {

  /**
   * Handles a location update POST.
   */
  public function update(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!$data || empty($data['assignment_id']) || !isset($data['lat']) || !isset($data['lng'])) {
      throw new BadRequestHttpException('Missing required fields: assignment_id, lat, lng');
    }

    $storage = $this->entityTypeManager()->getStorage('race_assignment');
    /** @var \Drupal\race_day\Entity\RaceAssignment|null $assignment */
    $assignment = $storage->load($data['assignment_id']);

    if (!$assignment) {
      throw new BadRequestHttpException('Assignment not found.');
    }

    // Verify the current user is the assigned runner.
    $current_user = $this->currentUser();
    $runner_id = $assignment->get('runner')->target_id;
    if ((int) $runner_id !== (int) $current_user->id()) {
      throw new BadRequestHttpException('You are not the assigned runner for this leg.');
    }

    $assignment->set('current_lat', $data['lat']);
    $assignment->set('current_lng', $data['lng']);
    $assignment->set('location_updated', \Drupal::time()->getRequestTime());

    if (isset($data['speed'])) {
      $assignment->set('speed', $data['speed']);
    }

    $assignment->save();

    return new JsonResponse([
      'status' => 'ok',
      'assignment_id' => $assignment->id(),
      'updated' => \Drupal::time()->getRequestTime(),
    ]);
  }

}



