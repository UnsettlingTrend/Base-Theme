<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ut_robinhood\Service\RobinhoodOrderImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes a Robinhood order import job queued by hook_cron().
 *
 * Using a queue worker decouples the import from the cron execution window.
 * Each queue item triggers one full import run via RobinhoodOrderImporter.
 *
 * The queue is named 'ut_robinhood_import' and must match the queue name used
 * in hook_cron() inside ut_robinhood.module.
 */
#[QueueWorker(
  id: 'ut_robinhood_import',
  title: new TranslatableMarkup('UT Robinhood: Import Stock Orders'),
  cron: ['time' => 120],
)]
class RobinhoodImportWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly RobinhoodOrderImporter $importer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ut_robinhood.order_importer'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array{triggered_by: string} $data
   *   The queue item payload created in hook_cron().
   */
  public function processItem(mixed $data): void {
    $this->importer->import();
  }

}
