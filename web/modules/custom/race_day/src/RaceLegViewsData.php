<?php

declare(strict_types=1);

namespace Drupal\race_day;

use Drupal\views\EntityViewsData;

/**
 * Provides custom Views data for the Race Leg entity.
 */
class RaceLegViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    $base_table = $this->entityType->getBaseTable();

    // Allow leg-based Views to pull runner assignments without manual SQL.
    $data[$base_table]['race_assignments'] = [
      'title' => $this->t('Runner assignments'),
      'help' => $this->t('Relate a race leg to its runner assignments.'),
      'relationship' => [
        'id' => 'standard',
        'base' => 'race_assignment',
        'base field' => 'leg_id',
        'field' => 'id',
        'label' => $this->t('Runner assignments'),
      ],
    ];

    return $data;
  }

}

