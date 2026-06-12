<?php

declare(strict_types=1);

namespace Drupal\race_day;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Race entities.
 */
class RaceListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['name'] = $this->t('Race');
    $header['race_date'] = $this->t('Date');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\race_day\Entity\Race $entity */
    $row['name'] = $entity->toLink();
    $row['race_date'] = $entity->get('race_date')->value ?? '';
    $row['status'] = $entity->get('status')->value ?? '';
    return $row + parent::buildRow($entity);
  }

}

