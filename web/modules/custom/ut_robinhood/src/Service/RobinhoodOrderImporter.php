<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\ut_robinhood\Entity\RobinhoodOrder;

/**
 * Imports Robinhood stock orders via the Python robin_stocks bridge script.
 *
 * ## How it works
 *
 * 1. Reads credentials from settings.php / environment variables (never config).
 * 2. Invokes scripts/rh_fetch_orders.py via shell_exec(), passing credentials
 *    and the pickle directory as arguments.
 * 3. Parses the JSON array of order objects returned on STDOUT.
 * 4. For each order, upserts a RobinhoodOrder entity keyed on robinhood_order_id.
 * 5. Writes a row to ut_robinhood_import_log.
 *
 * ## Security notes
 *
 * - Credentials are passed to the Python script via environment variables set
 *   on the proc_open() call, NOT via command-line arguments, so they do not
 *   appear in the process table.
 * - The pickle directory should be outside the web root.
 */
class RobinhoodOrderImporter {

  /**
   * The logger channel for the ut_robinhood module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs a new RobinhoodOrderImporter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager, used to load and create RobinhoodOrder entities.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory, used to read 'ut_robinhood.settings'.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory, used to create the 'ut_robinhood' logger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection, used for the import log table and start date queries.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service, used for timestamping import log entries.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerFactory,
    protected readonly Connection $database,
    protected readonly TimeInterface $time,
  ) {
    $this->logger = $loggerFactory->get('ut_robinhood');
  }

  // ------------------------------------------------------------------ //
  // Public API                                                           //
  // ------------------------------------------------------------------ //

  /**
   * Runs a full import cycle.
   *
   * @return array{created: int, updated: int, skipped: int, errors: int}
   *   Summary counts for the run.
   */
  public function import(): array {
    $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

    try {
      $raw_orders = $this->fetchOrdersFromPython();
    }
    catch (\RuntimeException $e) {
      $this->logger->error('Bridge script failed: @msg', ['@msg' => $e->getMessage()]);
      $this->logRun(0, 0, 0, 'error', $e->getMessage());
      return $stats;
    }

    $config       = $this->configFactory->get('ut_robinhood.settings');
    $allowed_states = array_keys(array_filter($config->get('order_states') ?: ['filled' => 'filled']));
    $update_existing = (bool) ($config->get('update_existing') ?? TRUE);
    $limit        = (int) ($config->get('import_limit') ?: 0);

    $processed = 0;
    foreach ($raw_orders as $raw) {
      if ($limit > 0 && $processed >= $limit) {
        break;
      }

      // Filter by allowed state.
      $state = $raw['state'] ?? '';
      if (!empty($allowed_states) && !in_array($state, $allowed_states, TRUE)) {
        $stats['skipped']++;
        continue;
      }

      try {
        $result = $this->upsertOrder($raw, $update_existing);
        $stats[$result]++;
      }
      catch (\Exception $e) {
        $stats['errors']++;
        $this->logger->error(
          'Failed to upsert order @id: @msg',
          ['@id' => $raw['id'] ?? 'unknown', '@msg' => $e->getMessage()],
        );
      }

      $processed++;
    }

    $total_found = count($raw_orders);
    $this->logRun($total_found, $stats['created'], $stats['updated'], 'success', NULL);

    $this->logger->info(
      'Import complete. Found: @found | Created: @created | Updated: @updated | Skipped: @skipped | Errors: @errors',
      [
        '@found'   => $total_found,
        '@created' => $stats['created'],
        '@updated' => $stats['updated'],
        '@skipped' => $stats['skipped'],
        '@errors'  => $stats['errors'],
      ],
    );

    return $stats;
  }

  // ------------------------------------------------------------------ //
  // Python bridge                                                        //
  // ------------------------------------------------------------------ //

  /**
   * Invokes the Python bridge script and returns the decoded order array.
   *
   * Credentials are passed via environment variables on the subprocess, not
   * as command-line arguments, so they never appear in `ps` output.
   *
   * @return array<int, array<string, mixed>>
   *   Array of raw order dictionaries as decoded from the script's JSON output.
   *
   * @throws \RuntimeException
   *   If the script cannot be found, fails to execute, or returns invalid JSON.
   */
  protected function fetchOrdersFromPython(): array {
    $script = $this->resolveBridgeScriptPath();
    $python = $this->resolvePythonBin();
    $pickle_dir = $this->resolvePickleDir();

    // Build environment for the subprocess. Credentials come from
    // settings.php or env vars and are forwarded to the Python process.
    $env = array_merge(getenv() ?: [], [
      'RH_USERNAME'    => $this->getSetting('ut_robinhood_username', 'UT_ROBINHOOD_USERNAME'),
      'RH_PASSWORD'    => $this->getSetting('ut_robinhood_password', 'UT_ROBINHOOD_PASSWORD'),
      'RH_MFA_CODE'    => $this->getSetting('ut_robinhood_mfa_code', 'UT_ROBINHOOD_MFA_CODE') ?? '',
      'RH_PICKLE_DIR'  => $pickle_dir,
      'RH_ACCOUNT_IDS' => $this->getSetting('ut_robinhood_account_ids', 'UT_ROBINHOOD_ACCOUNT_IDS') ?? '',
      'RH_START_DATE'  => $this->resolveStartDate(),
      'RH_MFA_WAIT'    => $this->getSetting('ut_robinhood_mfa_wait', 'UT_ROBINHOOD_MFA_WAIT') ?? '15',
    ]);

    // Build the env string for proc_open.
    $descriptor_spec = [
      0 => ['pipe', 'r'],  // stdin
      1 => ['pipe', 'w'],  // stdout  – JSON output
      2 => ['pipe', 'w'],  // stderr  – error messages
    ];

    $cmd = escapeshellarg($python) . ' ' . escapeshellarg($script);
    $process = proc_open($cmd, $descriptor_spec, $pipes, NULL, $env);

    if (!is_resource($process)) {
      throw new \RuntimeException("Failed to open Python process for command: $cmd");
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
      throw new \RuntimeException(
        "Bridge script exited with code $exit_code. STDERR: " . trim($stderr)
      );
    }

    $orders = json_decode($stdout, TRUE);
    if (!is_array($orders)) {
      // robin_stocks may print progress messages (e.g. "Loading page 2 ...")
      // to stdout before the JSON array. Strip everything before the first '['.
      $json_start = strpos($stdout, '[');
      if ($json_start !== FALSE && $json_start > 0) {
        $stdout = substr($stdout, $json_start);
        $orders = json_decode($stdout, TRUE);
      }
    }
    if (!is_array($orders)) {
      throw new \RuntimeException(
        'Bridge script did not return a valid JSON array. Output: ' . substr($stdout, 0, 500)
      );
    }

    // Save the raw JSON response to the private directory for archival.
    $this->saveRawJson($stdout);

    return $orders;
  }

  // ------------------------------------------------------------------ //
  // Entity upsert                                                        //
  // ------------------------------------------------------------------ //

  /**
   * Creates or updates a RobinhoodOrder entity from a raw order array.
   *
   * @param array<string, mixed> $raw
   *   A single order dictionary as returned by the bridge script.
   * @param bool $update_existing
   *   Whether to update fields on already-imported orders.
   *
   * @return string
   *   'created', 'updated', or 'skipped'.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function upsertOrder(array $raw, bool $update_existing): string {
    $rh_id = $raw['id'] ?? NULL;
    if (!$rh_id) {
      throw new \InvalidArgumentException('Order is missing the Robinhood ID field.');
    }

    $storage = $this->entityTypeManager->getStorage('robinhood_order');

    // Look up by Robinhood order ID (our deduplication key).
    $existing = $storage->loadByProperties(['robinhood_order_id' => $rh_id]);
    /** @var \Drupal\ut_robinhood\Entity\RobinhoodOrder|null $entity */
    $entity = $existing ? reset($existing) : NULL;

    if ($entity !== NULL && !$update_existing) {
      return 'skipped';
    }

    $values = $this->mapRawToEntityValues($raw);

    if ($entity === NULL) {
      $entity = $storage->create($values);
      $entity->save();
      return 'created';
    }

    // Update mutable fields only (leave Drupal id/uuid/created alone).
    $mutable_fields = [
      'order_state', 'quantity', 'price', 'average_price',
      'total_notional_value', 'fees', 'updated_at_robinhood',
      'account_name',
    ];
    foreach ($mutable_fields as $field) {
      if (isset($values[$field])) {
        $entity->set($field, $values[$field]);
      }
    }
    $entity->save();
    return 'updated';
  }

  /**
   * Maps a raw Robinhood API order array to RobinhoodOrder entity field values.
   *
   * Field mapping reference:
   *   Robinhood API key          → Entity field
   *   id                         → robinhood_order_id
   *   side                       → order_side
   *   type                       → order_type
   *   state                      → order_state
   *   quantity                   → quantity
   *   price                      → price
   *   average_price              → average_price
   *   total_notional.amount      → total_notional_value
   *   fees                       → fees
   *   time_in_force              → time_in_force
   *   created_at                 → created_at_robinhood
   *   updated_at                 → updated_at_robinhood
   *   trigger                    → trigger
   *   extended_hours             → extended_hours
   *   instrument (URL → ID)      → instrument_id
   *
   * @param array<string, mixed> $raw
   *   Raw order from the bridge script.
   *
   * @return array<string, mixed>
   *   Values array suitable for passing to EntityStorage::create() or Entity::set().
   */
  protected function mapRawToEntityValues(array $raw): array {
    // Extract account ID from the account URL.
    // e.g. https://api.robinhood.com/accounts/XXXXXXXX/
    $account_url = $raw['account'] ?? '';
    $account_id = '';
    if ($account_url) {
      $parts = array_filter(explode('/', rtrim($account_url, '/')));
      $account_id = end($parts) ?: '';
    }

    // Extract instrument UUID from the instrument URL.
    // Robinhood instrument URLs look like:
    // https://api.robinhood.com/instruments/450dfc6d-5510-4d40-abfb-f633b7d9be3e/
    $instrument_url = $raw['instrument'] ?? '';
    $instrument_id = '';
    if ($instrument_url) {
      $parts = array_filter(explode('/', rtrim($instrument_url, '/')));
      $instrument_id = end($parts) ?: '';
    }

    // robin_stocks resolves the symbol via a secondary API call and includes
    // it as 'symbol' in the output. Fall back to instrument_id if absent.
    $symbol = $raw['symbol'] ?? $instrument_id;

    // Normalise the Robinhood ISO 8601 timestamp to the format Drupal's
    // datetime field expects: 'Y-m-d\TH:i:s'.
    $created_at = $this->normaliseTimestamp($raw['created_at'] ?? NULL);
    $updated_at = $this->normaliseTimestamp($raw['updated_at'] ?? NULL);

    // total_notional is a nested object in some API versions.
    $total = NULL;
    if (isset($raw['total_notional']['amount'])) {
      $total = $raw['total_notional']['amount'];
    }
    elseif (isset($raw['executed_notional']['amount'])) {
      $total = $raw['executed_notional']['amount'];
    }

    return [
      'robinhood_order_id'   => $raw['id'],
      'symbol'               => strtoupper($symbol),
      'order_side'           => $raw['side'] ?? '',
      'order_type'           => $raw['type'] ?? '',
      'order_state'          => $raw['state'] ?? '',
      'quantity'             => $raw['quantity'] ?? NULL,
      'price'                => $raw['price'] ?? NULL,
      'average_price'        => $raw['average_price'] ?? NULL,
      'total_notional_value' => $total,
      'fees'                 => $raw['fees'] ?? NULL,
      'time_in_force'        => $raw['time_in_force'] ?? NULL,
      'created_at_robinhood' => $created_at,
      'updated_at_robinhood' => $updated_at,
      'trigger'              => $raw['trigger'] ?? NULL,
      'extended_hours'       => (bool) ($raw['extended_hours'] ?? FALSE),
      'instrument_id'        => $instrument_id,
      'account_id'           => $account_id,
      'account_name'         => $raw['account_name'] ?? '',
    ];
  }

  // ------------------------------------------------------------------ //
  // JSON archival                                                         //
  // ------------------------------------------------------------------ //

  /**
   * Saves the raw JSON response from Robinhood to the private directory.
   *
   * Files are timestamped so each import run is preserved for debugging or
   * auditing. Saved to private://ut_robinhood/orders_YYYY-MM-DD_HHmmss.json.
   */
  protected function saveRawJson(string $json): void {
    try {
      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');
      $dir = 'private://ut_robinhood';
      $file_system->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $filename = 'orders_' . date('Y-m-d_His') . '.json';
      $destination = $dir . '/' . $filename;
      $file_system->saveData($json, $destination, FileSystemInterface::EXISTS_RENAME);

      $this->logger->info('Saved raw JSON to @path', ['@path' => $destination]);
    }
    catch (\Exception $e) {
      // Non-fatal: log but don't interrupt the import.
      $this->logger->warning('Failed to save raw JSON: @msg', ['@msg' => $e->getMessage()]);
    }
  }

  // ------------------------------------------------------------------ //
  // Helpers                                                              //
  // ------------------------------------------------------------------ //

  /**
   * Converts a Robinhood ISO 8601 timestamp string to Drupal datetime format.
   *
   * Robinhood returns timestamps like '2024-03-15T14:32:01.123456Z'.
   * Drupal's datetime field stores 'Y-m-d\TH:i:s' in UTC.
   */
  protected function normaliseTimestamp(?string $timestamp): ?string {
    if (!$timestamp) {
      return NULL;
    }
    try {
      $dt = new \DateTimeImmutable($timestamp, new \DateTimeZone('UTC'));
      return $dt->format('Y-m-d\TH:i:s');
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Resolves the absolute path to the Python bridge script.
   *
   * @throws \RuntimeException
   */
  protected function resolveBridgeScriptPath(): string {
    $config_path = $this->configFactory->get('ut_robinhood.settings')->get('bridge_script_path');
    if ($config_path && file_exists($config_path)) {
      return $config_path;
    }

    // Default: scripts/ directory inside this module.
    $module_path = \Drupal::service('extension.list.module')->getPath('ut_robinhood');
    $default = DRUPAL_ROOT . '/' . $module_path . '/scripts/rh_fetch_orders.py';

    if (!file_exists($default)) {
      throw new \RuntimeException(
        "Python bridge script not found at: $default. " .
        "Check ut_robinhood settings or ensure scripts/rh_fetch_orders.py exists."
      );
    }

    return $default;
  }

  /**
   * Resolves the Python executable path.
   */
  protected function resolvePythonBin(): string {
    return $this->getSetting('ut_robinhood_python_bin', 'UT_ROBINHOOD_PYTHON_BIN') ?? 'python3';
  }

  /**
   * Resolves the directory for the robin_stocks auth pickle file.
   */
  protected function resolvePickleDir(): string {
    $config_dir = $this->configFactory->get('ut_robinhood.settings')->get('pickle_dir');
    if ($config_dir) {
      return $config_dir;
    }

    // Default: private files directory.
    $private = \Drupal::service('file_system')->realpath('private://');
    if (!$private) {
      // Fall back to a temp directory if private stream not configured.
      $private = sys_get_temp_dir();
    }

    $dir = $private . '/ut_robinhood';
    if (!is_dir($dir)) {
      mkdir($dir, 0750, TRUE);
    }

    return $dir;
  }

  /**
   * Reads a value from settings.php first, then falls back to an env var.
   *
   * @param string $settings_key
   *   The $settings[] key in settings.php.
   * @param string $env_var
   *   The environment variable name to fall back to.
   *
   * @return string|null
   *   The credential value, or NULL if not set in either source.
   */
  protected function getSetting(string $settings_key, string $env_var): ?string {
    $value = Settings::get($settings_key);
    if ($value !== NULL && $value !== '') {
      return (string) $value;
    }
    $env = getenv($env_var);
    return ($env !== FALSE && $env !== '') ? $env : NULL;
  }

  /**
   * Resolves the start date for incremental imports.
   *
   * Looks up the last successful import timestamp from the import log. If
   * found, returns a date 24 hours before that timestamp (to catch any orders
   * that may have been updated in the overlap window). On the very first
   * import (no log entries), returns an empty string so all orders are fetched.
   *
   * @return string
   *   A date string in 'Y-m-d' format, or '' for a full import.
   */
  protected function resolveStartDate(): string {
    try {
      $last_success = $this->database->select('ut_robinhood_import_log', 'l')
        ->fields('l', ['imported'])
        ->condition('status', 'success')
        ->orderBy('imported', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchField();

      if ($last_success) {
        // Subtract 24 hours for a safety overlap window.
        $start = (int) $last_success - 86400;
        return date('Y-m-d', $start);
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Could not query import log for start date: @msg', ['@msg' => $e->getMessage()]);
    }

    // First import or query failed — fetch everything.
    return '';
  }

  /**
   * Writes a row to the import log table.
   */
  protected function logRun(
    int $found,
    int $created,
    int $updated,
    string $status,
    ?string $message,
  ): void {
    $this->database->insert('ut_robinhood_import_log')
      ->fields([
        'imported'       => $this->time->getRequestTime(),
        'orders_found'   => $found,
        'orders_created' => $created,
        'orders_updated' => $updated,
        'status'         => $status,
        'message'        => $message,
      ])
      ->execute();
  }

}
