<?php

declare(strict_types=1);

namespace Drupal\race_day\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Displays the number of members in a race_team group.
 *
 * Computed with a scalar COUNT subquery rather than Group module's virtual
 * group_relationship_id relationship chain, which produces invalid SQL
 * (empty column alias) on DISTINCT queries — the same issue
 * RaceTeamMemberFilter and RaceTeamCurrentUserMemberFilter work around.
 *
 * @ViewsField("race_team_member_count")
 */
class RaceTeamMemberCountField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    // 'group_membership' is the relationship's CONTENT PLUGIN ID, not the
    // relationship TYPE's config entity ID — see the same note in
    // RaceTeamMemberFilter/RaceTeamCurrentUserMemberFilter.
    $this->field_alias = $this->query->addField(
      NULL,
      "(SELECT COUNT(*) FROM {group_relationship_field_data} rtmc_gr WHERE rtmc_gr.gid = $this->tableAlias.id AND rtmc_gr.plugin_id = 'group_membership')",
      'race_team_member_count'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): int {
    return (int) $this->getValue($values);
  }

}
