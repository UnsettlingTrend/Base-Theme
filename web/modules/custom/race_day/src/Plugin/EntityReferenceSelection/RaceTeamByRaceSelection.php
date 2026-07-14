<?php

namespace Drupal\race_day\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Entity reference selection plugin for race_team groups scoped to a race.
 *
 * Restricts selectable race_team groups to those whose field_race matches
 * the 'race_id' selection setting, on top of the normal access-checked
 * DefaultSelection behavior (target bundles, sorting, entity access).
 *
 * @EntityReferenceSelection(
 *   id = "race_day:race_team_by_race",
 *   label = @Translation("Race team by race (race_day)"),
 *   entity_types = {"group"},
 *   group = "race_day",
 *   weight = 0
 * )
 */
class RaceTeamByRaceSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    $race_id = $this->getConfiguration()['race_id'] ?? NULL;
    if ($race_id) {
      $query->condition('field_race', $race_id);
    }

    return $query;
  }

}
