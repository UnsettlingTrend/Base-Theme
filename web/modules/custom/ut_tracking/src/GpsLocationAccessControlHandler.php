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
 * "administer ut_tracking" can view, update, or delete any report. Beyond
 * that, viewing (only viewing — not update/delete) is also allowed for
 * anyone _ut_tracking_user_can_view_location() grants access to via the
 * report owner's Location Access group (see ut_tracking.module): a direct
 * member of that group, or a member of a race_team group the owner has
 * listed in it. There's still no self-service viewing of one's *own*
 * location history beyond that — reporting a location happens exclusively
 * through LocationReportController, gated by the separate "report own gps
 * location" permission checked on the route itself, not through entity
 * create access.
 */
class GpsLocationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer ut_tracking')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($operation !== 'view') {
      return AccessResult::neutral()->cachePerPermissions();
    }

    /** @var \Drupal\ut_tracking\Entity\GpsLocation $entity */
    $owner = $entity->getOwner();

    // Not cached: this depends on Location Access group membership and its
    // Authorized Race Teams field, and race_team group membership on top of
    // that — none of which reliably invalidate this entity's, or the
    // owner's, own cache tags when they change. Wrong-but-cached "denied"
    // (newly granted access not showing up) or "allowed" (revoked access
    // still working) are both worse than re-checking on every request for
    // an entity type with this little traffic.
    return AccessResult::allowedIf($owner && _ut_tracking_user_can_view_location($account, $owner))
      ->setCacheMaxAge(0);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'report own gps location');
  }

}
