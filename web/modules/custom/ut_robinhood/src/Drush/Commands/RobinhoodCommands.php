<?php
declare(strict_types=1);
namespace Drupal\ut_robinhood\Drush\Commands;
use Drupal\ut_robinhood\Service\RobinhoodOrderImporter;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Drush commands for the UT Robinhood module.
 */
final class RobinhoodCommands extends DrushCommands {
  public function __construct(
    protected readonly RobinhoodOrderImporter $importer,
  ) {
    parent::__construct();
  }
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('ut_robinhood.order_importer'),
    );
  }
  /**
   * Import Robinhood stock orders.
   *
   * Authenticates with the Robinhood API via the Python bridge script and
   * imports all stock orders as RobinhoodOrder entities.
   */
  #[CLI\Command(name: 'ut:robinhood:import', aliases: ['rh-import'])]
  #[CLI\Usage(name: 'drush ut:robinhood:import', description: 'Run a full Robinhood order import.')]
  public function import(): void {
    $this->io()->title('Robinhood Order Import');
    try {
      $stats = $this->importer->import();
    }
    catch (\Exception $e) {
      throw $e;
    }
    $this->io()->definitionList(
      ['Created' => $stats['created']],
      ['Updated' => $stats['updated']],
      ['Skipped' => $stats['skipped']],
      ['Errors'  => $stats['errors']],
    );
    $total = $stats['created'] + $stats['updated'];
    if ($stats['errors'] > 0) {
      $this->io()->warning("Import finished with {$stats['errors']} error(s). Check the log for details.");
    }
    elseif ($total === 0) {
      $this->io()->note('No new or updated orders found.');
    }
    else {
      $this->io()->success("Import complete: {$total} order(s) processed.");
    }
  }
}
