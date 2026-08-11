<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Lets a user set their own GPS tracking retention period, overriding the
 * site default set at /admin/tracking/settings for their own reports only.
 *
 * @see ut_tracking_cron()
 * @see ut_tracking_user_retention_allowed_values()
 */
class UserRetentionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ut_tracking_user_retention_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    $form['#user'] = $user;

    $form['retention_period'] = [
      '#type' => 'select',
      '#title' => $this->t('Keep my GPS location reports for'),
      '#description' => $this->t('Overrides the site default for your own reports only. Choose "Site default" to always follow whatever the site administrator has configured, even if it changes later. Your single most recent report is never deleted, regardless of this setting.'),
      '#options' => ut_tracking_user_retention_allowed_values(NULL, $user, $cacheable),
      '#default_value' => $user->get('field_ut_tracking_retention')->value ?: 'default',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\user\UserInterface $user */
    $user = $form['#user'];
    $user->set('field_ut_tracking_retention', $form_state->getValue('retention_period'));
    $user->save();
    $this->messenger()->addStatus($this->t('Your GPS tracking preference has been saved.'));
  }

}
