<?php

declare(strict_types=1);

namespace Drupal\race_day\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Confirmation form to cancel a race team join request.
 *
 * Reachable only via the "Cancel" operation added by
 * race_day_entity_operation() in race_day.module, which already gates
 * visibility on _race_day_can_cancel_team_request() — this form's own
 * access() callback re-checks the same rule directly on the route.
 */
class CancelTeamRequestForm extends ConfirmFormBase {

  protected ?WebformSubmissionInterface $submission = NULL;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'race_day_cancel_team_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t("Are you sure you want to cancel this request? This cannot be undone, and you'll have to create a new request if needed.");
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Cancel Request');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('race_day.my_teams');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?WebformSubmissionInterface $webform_submission = NULL): array {
    $this->submission = $webform_submission;
    return parent::buildForm($form, $form_state);
  }

  /**
   * Access callback for the race_day.cancel_team_request route.
   */
  public function access(AccountInterface $account, WebformSubmissionInterface $webform_submission): AccessResult {
    return AccessResult::allowedIf(_race_day_can_cancel_team_request($webform_submission, $account))
      ->addCacheableDependency($webform_submission)
      ->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($this->submission) {
      $this->submission->setElementData('request_status', 'canceled');
      // Saving triggers race_day_webform_submission_update(), which removes
      // group membership (and clears any leg assignment) if the request had
      // already been approved.
      $this->submission->save();
    }

    $this->messenger()->addStatus($this->t('Your request has been canceled.'));
    $redirect_url = $this->getCancelUrl();

    // Opened as an ajax dialog (see race_day_entity_operation()) — a plain
    // redirect here would navigate the dialog's iframe-less ajax context
    // rather than the real page, so close the modal and redirect the actual
    // browser window instead. Falls back to a normal redirect for the
    // no-JS case.
    if ($this->getRequest()->isXmlHttpRequest()) {
      $response = new AjaxResponse();
      $response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new RedirectCommand($redirect_url->toString()));
      $form_state->setResponse($response);
      return;
    }

    $form_state->setRedirectUrl($redirect_url);
  }

}
