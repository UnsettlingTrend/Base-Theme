<?php

namespace Drupal\race_day\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Adds the group page access check to the group canonical route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('entity.group.canonical')) {
      $route->setRequirement('_race_day_group_page_access', 'TRUE');
    }
  }

}
