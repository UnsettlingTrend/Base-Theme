<?php

declare(strict_types=1);

namespace Drupal\ut_tracking;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for GPS Device entities (admin > content > GPS Devices).
 */
class GpsDeviceListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['owner'] = $this->t('Registered to');
    $header['device_name'] = $this->t('Device');
    $header['device_identifier'] = $this->t('Device ID');
    $header['last_seen'] = $this->t('Last seen');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\ut_tracking\Entity\GpsDevice $entity */
    $row['owner'] = $entity->getOwner()?->getDisplayName() ?? $this->t('Unknown');
    $row['device_name'] = $entity->get('device_name')->value ?: $this->t('n/a');
    $row['device_identifier'] = $entity->get('device_identifier')->value;
    $last_seen = (int) $entity->get('last_seen')->value;
    $row['last_seen'] = $last_seen ? \Drupal::service('date.formatter')->format($last_seen, 'short') : $this->t('unknown');
    return $row + parent::buildRow($entity);
  }

}
