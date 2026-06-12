<?php

declare(strict_types=1);

namespace Drupal\race_day;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Race Leg entities.
 */
class RaceLegListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['leg_number'] = $this->t('#');
    $header['label'] = $this->t('Leg');
    $header['distance'] = $this->t('Distance');
    $header['difficulty'] = $this->t('Difficulty');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\race_day\Entity\RaceLeg $entity */
    $row['leg_number'] = $entity->get('leg_number')->value ?? '';
    $row['label'] = $entity->get('label')->value ?? '';
    $row['distance'] = ($entity->get('distance')->value ?? '') . ' mi';
    $row['difficulty'] = $entity->get('difficulty')->value ?? '';
    return $row + parent::buildRow($entity);
  }

}

