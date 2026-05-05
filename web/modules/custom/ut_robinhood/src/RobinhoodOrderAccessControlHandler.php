<?php

declare(strict_types=1);

namespace Drupal\ut_robinhood;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for Robinhood Order entities.
 */
class RobinhoodOrderAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   *
   * Grants access based on the operation being performed:
   * - 'view': requires either 'view ut_robinhood orders' or 'administer ut_robinhood'.
   * - 'update': requires 'administer ut_robinhood' only.
   * - 'delete': requires either 'delete ut_robinhood orders' or 'administer ut_robinhood'.
   * - Any other operation: neutral (neither allow nor deny).
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermissions(
        $account,
        ['view ut_robinhood orders', 'administer ut_robinhood'],
        'OR',
      ),
      'update' => AccessResult::allowedIfHasPermission($account, 'administer ut_robinhood'),
      'delete' => AccessResult::allowedIfHasPermissions(
        $account,
        ['delete ut_robinhood orders', 'administer ut_robinhood'],
        'OR',
      ),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   *
   * Only users with the 'administer ut_robinhood' permission may create
   * new Robinhood Order entities via the add form.
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer ut_robinhood');
  }

}
