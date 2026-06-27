<?php

namespace Drupal\race_day\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\race_day\Entity\Race;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Renders the runner team request webform pre-filled with the current race.
 */
class RunnerTeamRequestController extends ControllerBase {

  public function form(Race $race): array {
    $webform = $this->entityTypeManager()
      ->getStorage('webform')
      ->load('race_day_runner_team_request');

    if (!$webform) {
      return ['#markup' => $this->t('The request form is not available.')];
    }

    $submission = WebformSubmission::create([
      'webform_id' => $webform->id(),
      'data' => ['race' => $race->id()],
    ]);

    return $this->entityFormBuilder()->getForm($submission, 'add');
  }

}
