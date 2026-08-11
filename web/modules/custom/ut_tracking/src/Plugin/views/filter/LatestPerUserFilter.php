<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Restricts a gps_location View to each user's single most recent report.
 *
 * Plain Views GROUP BY aggregation can't do this correctly: aggregating
 * "MAX(recorded)" per uid doesn't guarantee the SAME row's latitude/
 * longitude come along with it, since each aggregated column is computed
 * independently. This uses a correlated subquery instead — the standard
 * "greatest-n-per-group" approach — so the full row for the max-recorded
 * report is what actually comes back. Always active, no exposed value:
 * this View has exactly one reason to exist (the Full Map), not a
 * general-purpose admin search filter.
 *
 * @ViewsFilter("latest_per_user")
 */
class LatestPerUserFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canExpose(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $this->ensureMyTable();
    $this->query->addWhereExpression(
      $this->options['group'],
      "$this->tableAlias.recorded = (SELECT MAX(gl2.recorded) FROM {gps_location} gl2 WHERE gl2.uid = $this->tableAlias.uid)"
    );
  }

}
