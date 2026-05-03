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

  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
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
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ut_robinhood\Entity\RobinhoodOrder $entity */

    $avg_price = $entity->get('average_price')->value;
    $total     = $entity->get('total_notional_value')->value;

    // Format the Robinhood-native created timestamp.
    $rh_created_raw = $entity->get('created_at_robinhood')->value;
    $rh_created = $rh_created_raw
      ? $this->dateFormatter->format(strtotime($rh_created_raw), 'short')
      : $this->t('—');

    $row = [
      'symbol'     => Link::createFromRoute(
        $entity->get('symbol')->value ?? '—',
        'entity.robinhood_order.canonical',
        ['robinhood_order' => $entity->id()],
      ),
      'side'       => $entity->get('order_side')->value ?? '—',
      'type'       => $entity->get('order_type')->value ?? '—',
      'state'      => $entity->get('order_state')->value ?? '—',
      'quantity'   => $entity->get('quantity')->value ?? '—',
      'avg_price'  => $avg_price !== NULL ? '$' . number_format((float) $avg_price, 4) : '—',
      'total'      => $total !== NULL ? '$' . number_format((float) $total, 2) : '—',
      'rh_created' => $rh_created,
    ];

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Order the list by most-recently placed orders first.
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
