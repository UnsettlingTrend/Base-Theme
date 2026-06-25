<?php

namespace Drupal\race_day\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\race_day\Entity\Race;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Opens the race waiver webform in a modal before allowing team registration.
 */
class WaiverModalController extends ControllerBase {

  public function modal(Race $race, Request $request): array|AjaxResponse|RedirectResponse {
    $destination = Url::fromRoute('entity.group.add_form', ['group_type' => 'race_team'], [
      'query' => ['race_id' => $race->id()],
    ])->toString();

    // If the waiver has already been submitted (or no waiver is required),
    // skip the modal and redirect straight to the team registration form.
    if ($this->waiverAlreadySubmitted($race)) {
      if ($request->isXmlHttpRequest()) {
        $response = new AjaxResponse();
        $response->addCommand(new CloseDialogCommand());
        $response->addCommand(new RedirectCommand($destination));
        return $response;
      }
      return new RedirectResponse($destination);
    }

    // Render the first outstanding waiver webform.
    $waiver = $this->getOutstandingWaiver($race);
    $submission = WebformSubmission::create(['webform_id' => $waiver->id()]);
    $form = $this->entityFormBuilder()->getForm($submission, 'add');

    return [
      '#title' => $this->t('Participant Waiver'),
      'form' => $form,
    ];
  }

  /**
   * Returns TRUE if the current user has submitted every waiver on the race.
   */
  private function waiverAlreadySubmitted(Race $race): bool {
    return $this->getOutstandingWaiver($race) === NULL;
  }

  /**
   * Returns the first webform in field_waiver that the user has not submitted,
   * or NULL when all waivers are satisfied (including when the field is empty).
   */
  private function getOutstandingWaiver(Race $race) {
    if ($race->get('field_waiver')->isEmpty()) {
      return NULL;
    }
    $account = $this->currentUser();
    if ($account->isAnonymous()) {
      // Anonymous users cannot submit; treat the first waiver as outstanding.
      return $race->get('field_waiver')->entity;
    }
    $storage = $this->entityTypeManager()->getStorage('webform_submission');
    foreach ($race->get('field_waiver') as $item) {
      $webform = $item->entity;
      if (!$webform) {
        continue;
      }
      $existing = $storage->loadByProperties([
        'webform_id' => $webform->id(),
        'uid' => $account->id(),
      ]);
      if (empty($existing)) {
        return $webform;
      }
    }
    return NULL;
  }

}
