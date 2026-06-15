<?php

declare(strict_types=1);

namespace Drupal\race_day\Drush\Commands;

use Drupal\race_day\Service\RaceLegCsvService;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for the Race Day module.
 */
final class RaceDayCommands extends DrushCommands {

  const EXPORT = 'race-day:legs:export';
  const IMPORT = 'race-day:legs:import';

  public function __construct(
    protected readonly RaceLegCsvService $csvService,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): self {
    return new self($container->get('race_day.csv_service'));
  }

  // ---------------------------------------------------------------------------
  // Export
  // ---------------------------------------------------------------------------

  /**
   * Export race_leg entities to a CSV file.
   */
  #[CLI\Command(name: self::EXPORT, aliases: ['rd-legs-export'])]
  #[CLI\Argument(name: 'output', description: 'Path to write the CSV. Defaults to exports/race_legs.csv inside the Drupal root.')]
  #[CLI\Option(name: 'race-id', description: 'Only export legs belonging to this race ID.')]
  #[CLI\Usage(name: 'drush race-day:legs:export', description: 'Export all race legs to <drupal-root>/exports/race_legs.csv')]
  #[CLI\Usage(name: 'drush race-day:legs:export --race-id=1 /tmp/legs.csv', description: 'Export legs for race 1 to /tmp/legs.csv')]
  public function export(string $output = '', array $options = ['race-id' => NULL]): void {
    $output = $output ?: \Drupal::root() . '/exports/race_legs.csv';

    $dir = dirname($output);
    if (!is_dir($dir) && !mkdir($dir, 0755, TRUE)) {
      throw new \RuntimeException("Cannot create directory: $dir");
    }

    $race_id = !empty($options['race-id']) ? (int) $options['race-id'] : NULL;
    $csv     = $this->csvService->export($race_id);

    if (file_put_contents($output, $csv) === FALSE) {
      throw new \RuntimeException("Cannot write to: $output");
    }

    $line_count = max(0, substr_count($csv, "\n") - 1);
    $this->io()->success("Exported $line_count race leg(s) to $output");
  }

  // ---------------------------------------------------------------------------
  // Import
  // ---------------------------------------------------------------------------

  /**
   * Import race_leg entities from a CSV file.
   *
   * Rows are matched on race_id + leg_number. Existing legs are updated;
   * new legs (and their strava_route paragraphs) are created.
   */
  #[CLI\Command(name: self::IMPORT, aliases: ['rd-legs-import'])]
  #[CLI\Argument(name: 'input', description: 'Path to the CSV file to import.')]
  #[CLI\Option(name: 'delete-missing', description: 'Delete legs that exist in the DB but are absent from the CSV (scoped to each race_id present in the file).')]
  #[CLI\Usage(name: 'drush race-day:legs:import exports/race_legs.csv', description: 'Import/update race legs from CSV')]
  #[CLI\Usage(name: 'drush race-day:legs:import exports/race_legs.csv --delete-missing', description: 'Import and remove any legs not in the CSV')]
  public function import(string $input, array $options = ['delete-missing' => FALSE]): void {
    if (!file_exists($input)) {
      throw new \InvalidArgumentException("File not found: $input\nNote: paths must be resolvable inside the Lando container (e.g. /app/web/exports/race_legs.csv).");
    }

    $csv = file_get_contents($input);
    if ($csv === FALSE) {
      throw new \RuntimeException("Cannot read: $input");
    }

    $this->io()->title('Importing race legs from ' . basename($input));

    $stats = $this->csvService->import($csv, (bool) $options['delete-missing']);

    foreach ($stats['messages'] as $msg) {
      $this->logger()->warning($msg);
    }

    $this->io()->definitionList(
      ['Created' => $stats['created']],
      ['Updated' => $stats['updated']],
      ['Skipped' => $stats['skipped']],
      ['Errors'  => $stats['errors']],
    );

    if ($stats['errors'] > 0) {
      $this->io()->warning("Import finished with {$stats['errors']} error(s). Check the log for details.");
    }
    else {
      $this->io()->success('Import complete.');
    }
  }

}
