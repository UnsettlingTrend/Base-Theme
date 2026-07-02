<?php

namespace Drupal\race_day\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Entity reference selection plugin for race_team groups.
 *
 * Bypasses the Group module's member-only access conditions so that any user
 * with 'view all race team names' can see all published race_team groups in
 * selection fields, without granting them access to the entity page itself.
 *
 * @EntityReferenceSelection(
 *   id = "race_day:race_team_name",
 *   label = @Translation("Race team name (race_day)"),
 *   entity_types = {"group"},
 *   group = "race_day",
 *   weight = 0
 * )
 */
class RaceTeamNameSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    // Build the query WITHOUT access checking so the Group module's member
    // restrictions do not filter out teams the user cannot "view" in the full
    // entity sense.
    $query = $this->entityTypeManager
      ->getStorage('group')
      ->getQuery()
      ->accessCheck(FALSE);

    // Restrict to race_team bundle.
    $query->condition('type', 'race_team');

    // Only published groups.
    $query->condition('status', 1);

    if ($match !== NULL) {
      $query->condition('label', $match, $match_operator);
    }

    // Apply target bundles from selection settings if provided.
    $configuration = $this->getConfiguration();
    if (!empty($configuration['target_bundles'])) {
      $query->condition('type', array_values($configuration['target_bundles']), 'IN');
    }

    $query->sort('label');

    return $query;
  }

}
