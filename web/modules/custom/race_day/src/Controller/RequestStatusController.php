<?php

namespace Drupal\race_day\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles AJAX updates to the race team request status field.
 */
class RequestStatusController extends ControllerBase {

  public function update(Request $request): JsonResponse {
    $sid = (int) $request->request->get('sid');
    $status = $request->request->get('status');

    if (!$sid || $status === NULL) {
      return new JsonResponse(['error' => 'Missing parameters.'], 400);
    }

    $webform_id = 'race_day_runner_team_request';
    $webform = $this->entityTypeManager()->getStorage('webform')->load($webform_id);
    if (!$webform) {
      return new JsonResponse(['error' => 'Webform not found.'], 404);
    }

    // Validate the status value against the webform element options.
    $element = $webform->getElement('request_status');
    $allowed = array_keys($element['#options'] ?? []);
    if (!in_array($status, $allowed, TRUE)) {
      return new JsonResponse(['error' => 'Invalid status value.'], 400);
    }

    $submission = $this->entityTypeManager()
      ->getStorage('webform_submission')
      ->load($sid);

    if (!$submission || $submission->getWebform()->id() !== $webform_id) {
      return new JsonResponse(['error' => 'Submission not found.'], 404);
    }

    $submission->setElementData('request_status', $status);
    $submission->save();

    return new JsonResponse(['status' => 'ok', 'new_value' => $status]);
  }

}
