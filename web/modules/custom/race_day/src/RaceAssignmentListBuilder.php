<?php

declare(strict_types=1);

namespace Drupal\race_day;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for Race Assignment entities.
 */
class RaceAssignmentListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['leg'] = $this->t('Leg');
    $header['runner'] = $this->t('Runner');
    $header['runner_status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\race_day\Entity\RaceAssignment $entity */
    $leg = $entity->get('leg_id')->entity;
    $runner = $entity->get('runner')->entity;

    $row['leg'] = $leg ? $leg->label() : '';
    $row['runner'] = $runner ? $runner->getDisplayName() : '';
    $row['runner_status'] = $entity->get('runner_status')->value ?? '';
    return $row + parent::buildRow($entity);
  }

}

