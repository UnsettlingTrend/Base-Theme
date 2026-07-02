<?php

namespace Drupal\race_day\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\Entity\GroupInterface;

/**
 * Blocks direct entity page access for users with only 'view all race team names'.
 *
 * Granting 'view' entity access is required so entity queries (webform
 * selects, views) can include race_team groups for these users, but we do not
 * want them landing on the full group entity page. A forbidden result here
 * wins over the allowed result from hook_entity_access().
 */
class GroupPageAccessCheck implements AccessInterface {

  public function access(AccountInterface $account, GroupInterface $group = NULL): AccessResultInterface {
    if (!$group || $group->bundle() !== 'race_team') {
      return AccessResult::neutral();
    }
    if ($account->hasPermission('view all race team names') && !$account->hasPermission('view all race day groups')) {
      return AccessResult::forbidden()->cachePerPermissions();
    }
    return AccessResult::neutral()->cachePerPermissions();
  }

}
