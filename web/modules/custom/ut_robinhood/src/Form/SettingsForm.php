<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ut_robinhood\Service\RobinhoodOrderImporter;

/**
 * Settings form for the UT Robinhood module.
 *
 * Credentials (username, password, MFA) are intentionally NOT stored in Drupal
 * config. They must be provided via settings.php or environment variables:
 *
 * @code
 * // In settings.php:
 * $settings['ut_robinhood_username'] = 'user@example.com';
 * $settings['ut_robinhood_password'] = 'secret';
 * $settings['ut_robinhood_mfa_code'] = '';  // Leave empty if using SMS/app.
 * $settings['ut_robinhood_python_bin'] = '/usr/bin/python3';
 * @endcode
 *
 * Or as environment variables:
 *   UT_ROBINHOOD_USERNAME, UT_ROBINHOOD_PASSWORD, UT_ROBINHOOD_MFA_CODE,
 *   UT_ROBINHOOD_PYTHON_BIN
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Robinhood order importer service.
   */
  protected RobinhoodOrderImporter $importer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->importer = $container->get('ut_robinhood.order_importer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ut_robinhood_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ut_robinhood.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ut_robinhood.settings');

    $form['credentials_notice'] = [
      '#type' => 'details',
      '#title' => $this->t('Credential Configuration'),
      '#open' => TRUE,
    ];

    $form['credentials_notice']['notice'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t(
        'Robinhood credentials are <strong>not</strong> stored in this form for security reasons.
         Provide them via <code>settings.php</code> or environment variables:<br><br>
         <code>$settings[\'ut_robinhood_username\'] = \'user@example.com\';</code><br>
         <code>$settings[\'ut_robinhood_password\'] = \'secret\';</code><br>
         <code>$settings[\'ut_robinhood_mfa_code\'] = \'\';</code> (leave empty unless pre-generating TOTP tokens)<br>
         <code>$settings[\'ut_robinhood_python_bin\'] = \'/usr/bin/python3\';</code><br><br>
         Or as environment variables: <code>UT_ROBINHOOD_USERNAME</code>, <code>UT_ROBINHOOD_PASSWORD</code>,
         <code>UT_ROBINHOOD_MFA_CODE</code>, <code>UT_ROBINHOOD_PYTHON_BIN</code>.'
      ) . '</p>',
    ];

    // Credential status readout (does NOT show values).
    $checks = [
      'Username'   => Settings::get('ut_robinhood_username') ?? getenv('UT_ROBINHOOD_USERNAME'),
      'Password'   => Settings::get('ut_robinhood_password') ?? getenv('UT_ROBINHOOD_PASSWORD'),
      'Python bin' => Settings::get('ut_robinhood_python_bin') ?? getenv('UT_ROBINHOOD_PYTHON_BIN'),
    ];

    $status_rows = [];
    foreach ($checks as $label => $value) {
      $status_rows[] = [
        $label,
        $value ? $this->t('✔ Configured') : $this->t('✘ Not set'),
      ];
    }

    $form['credentials_notice']['status'] = [
      '#type' => 'table',
      '#header' => [$this->t('Setting'), $this->t('Status')],
      '#rows' => $status_rows,
      '#empty' => $this->t('No credentials detected.'),
    ];

    $form['import'] = [
      '#type' => 'details',
      '#title' => $this->t('Import Settings'),
      '#open' => TRUE,
    ];

    $form['import']['cron_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Cron import interval'),
      '#description' => $this->t('How frequently the cron job will queue a Robinhood import. This is a minimum interval — cron must also run at least this often.'),
      '#options' => [
        900   => $this->t('Every 15 minutes'),
        1800  => $this->t('Every 30 minutes'),
        3600  => $this->t('Every hour'),
        7200  => $this->t('Every 2 hours'),
        21600 => $this->t('Every 6 hours'),
        43200 => $this->t('Every 12 hours'),
        86400 => $this->t('Once daily'),
      ],
      '#default_value' => $config->get('cron_interval') ?: 3600,
    ];

    $form['import']['order_states'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Import orders with these states'),
      '#description' => $this->t('Only orders matching at least one of these states will be created or updated as Drupal entities.'),
      '#options' => [
        'filled'           => $this->t('Filled'),
        'partially_filled' => $this->t('Partially Filled'),
        'confirmed'        => $this->t('Confirmed'),
        'queued'           => $this->t('Queued'),
        'cancelled'        => $this->t('Cancelled'),
        'rejected'         => $this->t('Rejected'),
        'failed'           => $this->t('Failed'),
      ],
      '#default_value' => $config->get('order_states') ?: ['filled'],
    ];

    $form['import']['update_existing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Update existing orders on re-import'),
      '#description' => $this->t('If checked, orders already imported will have their state and price fields refreshed on each run. If unchecked, only new orders are created.'),
      '#default_value' => $config->get('update_existing') ?? TRUE,
    ];

    $form['import']['import_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Max orders per import run'),
      '#description' => $this->t('Limit how many orders are processed per cron run. 0 = no limit (import all). Use this to avoid queue timeouts on large accounts.'),
      '#default_value' => $config->get('import_limit') ?: 0,
      '#min' => 0,
      '#step' => 1,
    ];

    $form['python'] = [
      '#type' => 'details',
      '#title' => $this->t('Python Bridge Settings'),
      '#open' => TRUE,
    ];

    $form['python']['bridge_script_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bridge script path'),
      '#description' => $this->t(
        'Absolute path to the <code>rh_fetch_orders.py</code> Python script included with this module.
         Defaults to <code>[module_dir]/scripts/rh_fetch_orders.py</code>. Override only if you have moved the script.'
      ),
      '#default_value' => $config->get('bridge_script_path') ?: '',
      '#placeholder' => $this->t('Leave blank to use the default module path.'),
    ];

    $form['python']['pickle_dir'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pickle / session cache directory'),
      '#description' => $this->t(
        'Directory where robin_stocks stores the auth pickle file (to avoid re-authenticating every run).
         Must be writable by the web server. Defaults to <code>[site_files]/private/ut_robinhood/</code>.'
      ),
      '#default_value' => $config->get('pickle_dir') ?: '',
      '#placeholder' => $this->t('Leave blank to use the default private files path.'),
    ];

    $form['import_now'] = [
      '#type' => 'details',
      '#title' => $this->t('Run Import Now'),
      '#open' => TRUE,
      '#weight' => 100,
    ];

    $form['import_now']['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Click the button below to immediately run a Robinhood order import using the current settings. This bypasses the cron schedule.') . '</p>',
    ];

    $form['import_now']['import_now_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import Now'),
      '#submit' => ['::importNowSubmit'],
      '#button_type' => 'primary',
      // Skip config validation for this button.
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ut_robinhood.settings')
      ->set('cron_interval', (int) $form_state->getValue('cron_interval'))
      ->set('order_states', array_filter($form_state->getValue('order_states')))
      ->set('update_existing', (bool) $form_state->getValue('update_existing'))
      ->set('import_limit', (int) $form_state->getValue('import_limit'))
      ->set('bridge_script_path', trim($form_state->getValue('bridge_script_path')))
      ->set('pickle_dir', trim($form_state->getValue('pickle_dir')))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for the "Import Now" button.
   *
   * Sets up a Batch API operation that shows a progress page, then redirects
   * back to this settings form on completion.
   */
  public function importNowSubmit(array &$form, FormStateInterface $form_state): void {
    $batch = [
      'title' => $this->t('Importing Robinhood Orders'),
      'operations' => [
        [[static::class, 'batchImport'], []],
      ],
      'finished' => [static::class, 'batchFinished'],
      'progress_message' => $this->t('Running import…'),
    ];
    batch_set($batch);
    $form_state->setRedirectUrl(Url::fromRoute('ut_robinhood.settings'));
  }

  /**
   * Batch operation callback: runs the import.
   */
  public static function batchImport(array &$context): void {
    /** @var \Drupal\ut_robinhood\Service\RobinhoodOrderImporter $importer */
    $importer = \Drupal::service('ut_robinhood.order_importer');
    try {
      $stats = $importer->import();
      $context['results']['stats'] = $stats;
      $context['results']['success'] = TRUE;
      $context['message'] = t('Processing orders…');
    }
    catch (\Exception $e) {
      $context['results']['success'] = FALSE;
      $context['results']['error'] = $e->getMessage();
    }
  }

  /**
   * Batch finished callback: shows result messages.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    $messenger = \Drupal::messenger();

    if (!$success || empty($results['success'])) {
      $error = $results['error'] ?? t('Unknown error');
      $messenger->addError(t('Import failed: @message', ['@message' => $error]));
      return;
    }

    $stats = $results['stats'];
    $messenger->addStatus(t(
      'Import complete. Created: @created | Updated: @updated | Skipped: @skipped | Errors: @errors',
      [
        '@created' => $stats['created'],
        '@updated' => $stats['updated'],
        '@skipped' => $stats['skipped'],
        '@errors'  => $stats['errors'],
      ]
    ));
    if ($stats['errors'] > 0) {
      $messenger->addWarning(t('Some orders had errors. Check the log for details.'));
    }
  }

}
