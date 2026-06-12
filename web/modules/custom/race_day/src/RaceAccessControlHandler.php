<?php

declare(strict_types=1);

namespace Drupal\race_day;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for Race Day entities.
 *
 * Shared by Race, RaceLeg, and RaceAssignment.
 */
class RaceAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    if ($account->hasPermission('administer race_day')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view race_day content'),
      'update' => AccessResult::allowedIfHasPermission($account, 'manage race_day races'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'manage race_day races'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['administer race_day', 'manage race_day races'], 'OR');
  }

}
