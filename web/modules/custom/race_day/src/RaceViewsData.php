<?php

declare(strict_types=1);

namespace Drupal\race_day;

use Drupal\views\EntityViewsData;

/**
 * Provides custom Views data for the Race entity.
 */
class RaceViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData(): array {
    $data = parent::getViewsData();

    $base_table = $this->entityType->getBaseTable();

    // Allow Views based on races to join directly to legs.
    $data[$base_table]['race_legs'] = [
      'title' => $this->t('Race legs'),
      'help' => $this->t('Relate a race to its legs.'),
      'relationship' => [
        'id' => 'standard',
        'base' => 'race_leg',
        'base field' => 'race_id',
        'field' => 'id',
        'label' => $this->t('Race legs'),
      ],
    ];

    return $data;
  }

}

