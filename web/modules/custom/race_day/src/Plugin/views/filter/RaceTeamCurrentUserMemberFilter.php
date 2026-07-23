<?php

declare(strict_types=1);

namespace Drupal\race_day\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters race_team groups to ones the current user is a member of.
 *
 * Same EXISTS-subquery approach as RaceTeamMemberFilter, and for the same
 * reason: Group module's virtual group_relationship_id relationship chain
 * produces invalid SQL (empty column alias) on DISTINCT queries. Always
 * active — not exposed, no configurable value — since it's used to power a
 * personal "my teams" listing rather than an admin search filter.
 *
 * @ViewsFilter("race_team_current_user_member")
 */
class RaceTeamCurrentUserMemberFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $uid = \Drupal::currentUser()->id();

    $subquery = \Drupal::database()->select('group_relationship_field_data', 'gr');
    $subquery->addField('gr', 'gid');
    // 'group_membership' is the relationship's CONTENT PLUGIN ID (stored in
    // this column) — not 'race_team-group_membership', which is the
    // relationship TYPE's config entity ID. Confirmed against a real
    // membership row; see the same mix-up in RaceTeamMemberFilter below.
    $subquery->condition('gr.plugin_id', 'group_membership');
    $subquery->condition('gr.entity_id', $uid);

    $this->query->addWhere(
      $this->options['group'],
      "$this->tableAlias.id",
      $subquery,
      'IN'
    );
  }

}
