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

    // The 'route' field is a code-defined base field, so core's generic
    // entity_reference_revisions Views integration (which only implements
    // hook_field_views_data() for Field API config-storage fields) never
    // runs for it. Add the relationship by hand, following the same shape
    // entity_reference_revisions_field_views_data() builds for config fields.
    $paragraph_type = $this->entityTypeManager->getDefinition('paragraph');
    $data[$base_table]['route__target_revision_id']['relationship'] = [
      'title' => $this->t('Route'),
      'label' => $this->t('Route'),
      'group' => $this->t('Race Leg'),
      'help' => $this->t('Relate a race leg to its embedded Strava route paragraph.'),
      'id' => 'standard',
      'base' => $paragraph_type->getDataTable() ?: $paragraph_type->getBaseTable(),
      'entity type' => 'paragraph',
      'base field' => $paragraph_type->getKey('revision'),
    ];

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

