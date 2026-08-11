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
 * Only "administer ut_tracking" can view or delete reports through the UI —
 * there's no self-service viewing of one's own location history here, since
 * that wasn't asked for. Reporting a location happens exclusively through
 * LocationReportController, gated by the separate "report own gps location"
 * permission checked on the route itself, not through entity create access.
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
