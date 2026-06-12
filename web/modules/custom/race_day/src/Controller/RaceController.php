<?php

declare(strict_types=1);

namespace Drupal\race_day\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\race_day\Entity\Race;

/**
 * Controller for Race entity pages.
 */
class RaceController extends ControllerBase {

  /**
   * Title callback for the race canonical route.
   */
  public function raceTitle(Race $race): string {
    return $race->label() ?? '';
  }

}

