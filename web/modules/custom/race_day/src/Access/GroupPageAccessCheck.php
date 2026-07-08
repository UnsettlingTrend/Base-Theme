<?php

namespace Drupal\race_day\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Controls race_team group entity page access.
 *
 * Allowed: administrators, Race Day Coordinators (view all race day groups),
 * and group members with the group-level 'view group' permission.
 * Forbidden: users with 'view all race team names' who don't meet the above.
 */
class GroupPageAccessCheck implements AccessInterface {

  public function access(AccountInterface $account, GroupInterface $group = NULL): AccessResultInterface {
    if (!$group || $group->bundle() !== 'race_team') {
      return AccessResult::allowed();
    }

    // Administrators and coordinators with the Drupal-level permission.
    if ($account->hasPermission('view all race day groups')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Group members whose group role grants 'view group'.
    if ($group->hasPermission('view group', $account)) {
      return AccessResult::allowed()
        ->addCacheContexts(['user.group_permissions'])
        ->addCacheableDependency($group);
    }

    // Users who can see team names but don't qualify above: block entity page.
    if ($account->hasPermission('view all race team names')) {
      return AccessResult::forbidden()->cachePerPermissions();
    }

    return AccessResult::neutral()->cachePerPermissions();
  }

}
