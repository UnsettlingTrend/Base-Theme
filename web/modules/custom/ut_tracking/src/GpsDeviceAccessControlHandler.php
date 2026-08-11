<?php

declare(strict_types=1);

namespace Drupal\ut_tracking;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the GPS Device entity.
 *
 * Mirrors GpsLocationAccessControlHandler: only "administer ut_tracking"
 * can view or delete device records through the UI. Devices are created
 * exclusively by LocationReportController on first report, gated by the
 * "report own gps location" permission checked on the route.
 */
class GpsDeviceAccessControlHandler extends EntityAccessControlHandler {

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
