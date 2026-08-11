<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures how long GPS location reports are kept before cron deletes them.
 *
 * @see ut_tracking_cron()
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ut_tracking.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ut_tracking_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ut_tracking.settings');

    $form['retention_period'] = [
      '#type' => 'radios',
      '#title' => $this->t('Keep GPS location reports for'),
      '#description' => $this->t("Reports older than this are permanently deleted the next time cron runs. Applies to reports already stored, not just new ones going forward. Each user's single most recent report is never deleted, regardless of this setting, so a user who stops reporting always still has at least one location on record. Users can override this for their own reports from their account's Tracking tab."),
      '#options' => ut_tracking_retention_period_labels(),
      '#default_value' => $config->get('retention_period') ?: 'forever',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ut_tracking.settings')
      ->set('retention_period', $form_state->getValue('retention_period'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
