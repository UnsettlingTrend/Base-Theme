<?php

declare(strict_types=1);

namespace Drupal\ut_tracking;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the GPS Location entity.
 *
 * Only "administer ut_tracking" can view, update, or delete a gps_location
 * entity directly, through any UI or API that goes through entity access —
 * that's every individual raw report, not just the current one. Location
 * Access group membership (see ut_tracking.module) deliberately does NOT
 * grant entity-level access here: it instead grants access to a much
 * narrower thing — a user's single *current* location, i.e. their most
 * recent gps_location record — surfaced through its own dedicated code
 * path rather than through this handler. See
 * _ut_tracking_user_can_view_location() for that check.
 */
class GpsLocationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'administer ut_tracking');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'report own gps location');
  }

}
