<?php

declare(strict_types=1);

namespace Drupal\race_day\Entity\Controller;

use Drupal\group\Entity\Controller\GroupListBuilder;

/**
 * List builder for group entities that bypasses Group's membership query filter
 * for users with the 'view all race day groups' permission.
 */
class RaceDayGroupListBuilder extends GroupListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    if (\Drupal::currentUser()->hasPermission('view all race day groups')) {
      $query = $this->getStorage()->getQuery();
      $header = $this->buildHeader();
      $query->tableSort($header);
      if ($this->limit) {
        $query->pager($this->limit);
      }
      return $query->accessCheck(FALSE)->execute();
    }
    return parent::getEntityIds();
  }

}
