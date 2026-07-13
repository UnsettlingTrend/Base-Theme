<?php

namespace Drupal\race_day\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles AJAX updates to the race team request status field.
 */
class RequestStatusController extends ControllerBase {

  /**
   * Access callback for the update route.
   *
   * Grants access if the user has any review permission. For
   * 'review own race team requests', also verifies the submission belongs to
   * a team the user is a member of.
   */
  public function access(AccountInterface $account, Request $request): AccessResult {
    if ($account->hasPermission('review all race team requests')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($account->hasPermission('review own race team requests')) {
      $sid = (int) $request->request->get('sid');
      if ($sid) {
        $submission = $this->entityTypeManager()
          ->getStorage('webform_submission')
          ->load($sid);
        if ($submission) {
          $team_id = (int) $submission->getElementData('race_team');
          if ($team_id) {
            $group = $this->entityTypeManager()->getStorage('group')->load($team_id);
            if ($group && $group->getMember($account)) {
              return AccessResult::allowed()->cachePerUser()->addCacheableDependency($submission);
            }
          }
        }
      }
      return AccessResult::forbidden()->cachePerUser();
    }

    return AccessResult::forbidden()->cachePerPermissions();
  }

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
