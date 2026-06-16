<?php

declare(strict_types=1);

namespace Drupal\race_day\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\StringFilter;

/**
 * Filters race_team groups by member username using an EXISTS subquery.
 *
 * This avoids the Group module's virtual group_relationship_id relationship
 * chain, which produces invalid SQL (empty column alias) on DISTINCT queries.
 *
 * @ViewsFilter("race_team_member_name")
 */
class RaceTeamMemberFilter extends StringFilter {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    if (empty($this->value)) {
      return;
    }

    $this->ensureMyTable();

    $value = '%' . $this->view->getQuery()->getConnection()->escapeLike($this->value) . '%';

    $subquery = \Drupal::database()->select('group_relationship_field_data', 'gr');
    $subquery->join('users_field_data', 'u', 'u.uid = gr.entity_id');
    $subquery->addField('gr', 'gid');
    $subquery->condition('gr.plugin_id', 'race_team-group_membership');
    $subquery->where("u.name LIKE :member_name", [':member_name' => $value]);

    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.id",
      $subquery,
      'IN'
    );
  }

}
