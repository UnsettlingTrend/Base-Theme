<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a list builder for Robinhood Order entities.
 *
 * Renders the admin table at /admin/content/robinhood-orders.
 */
class RobinhoodOrderListBuilder extends EntityListBuilder {

  /**
   * Constructs a new RobinhoodOrderListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage handler.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service, used to render Robinhood timestamps.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   *
   * Instantiates the list builder with the date formatter service injected.
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Builds the header row for the admin order listing table.
   */
  public function buildHeader(): array {
    $header = [
      'symbol'       => $this->t('Symbol'),
      'side'         => $this->t('Side'),
      'type'         => $this->t('Type'),
      'state'        => $this->t('State'),
      'quantity'     => $this->t('Qty'),
      'avg_price'    => $this->t('Avg Price'),
      'total'        => $this->t('Total Value'),
      'rh_created'   => $this->t('Order Date'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * Builds a single table row for a Robinhood Order entity, displaying the
   * ticker symbol (linked to the canonical page), side, type, state, quantity,
   * average fill price, total value, and Robinhood order date.
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ut_robinhood\Entity\RobinhoodOrder $entity */

    // Extract numeric values for formatting.
    $avg_price = $entity->get('average_price')->value;
    $total     = $entity->get('total_notional_value')->value;

    // Format the Robinhood-native created timestamp.
    $rh_created_raw = $entity->get('created_at_robinhood')->value;
    $rh_created = $rh_created_raw
      ? $this->dateFormatter->format(strtotime($rh_created_raw), 'short')
      : $this->t('—');

    $row = [
      // Link the symbol to the entity's canonical route.
      'symbol'     => Link::createFromRoute(
        $entity->get('symbol')->value ?? '—',
        'entity.robinhood_order.canonical',
        ['robinhood_order' => $entity->id()],
      ),
      'side'       => $entity->get('order_side')->value ?? '—',
      'type'       => $entity->get('order_type')->value ?? '—',
      'state'      => $entity->get('order_state')->value ?? '—',
      'quantity'   => $entity->get('quantity')->value ?? '—',
      // Format currency values with dollar sign; show em dash if null.
      'avg_price'  => $avg_price !== NULL ? '$' . number_format((float) $avg_price, 4) : '—',
      'total'      => $total !== NULL ? '$' . number_format((float) $total, 2) : '—',
      'rh_created' => $rh_created,
    ];

    // Merge with parent row to include operation links (edit/delete).
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Overrides the default entity ID query to sort orders by most-recently
   * placed first (descending Robinhood created timestamp), with a secondary
   * sort on entity ID for deterministic ordering of same-timestamp orders.
   */
  protected function getEntityIds(): array {
    $query = $this->getStorage()->getQuery()
      ->accessCheck(TRUE)
      ->sort('created_at_robinhood', 'DESC')
      ->sort('id', 'DESC');

    // Only add pager if limit is set.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

}
