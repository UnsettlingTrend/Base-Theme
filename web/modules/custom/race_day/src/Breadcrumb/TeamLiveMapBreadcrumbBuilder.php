<?php

declare(strict_types=1);

namespace Drupal\race_day\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group\Entity\GroupInterface;

/**
 * Builds "Home > Race Name > Team Name > Live Map" for the team live map.
 *
 * The default trail (easy_breadcrumb, priority 1003 — see
 * easy_breadcrumb.services.yml) has no way to know a race_team group's own
 * race, so it falls back to just "Home > <page title>" here. This replaces
 * it for this one route only (applies() below), at a higher priority, with
 * the race and team each linked back to their own page.
 */
class TeamLiveMapBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match): bool {
    return $route_match->getRouteName() === 'race_day.team_live_map';
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match): Breadcrumb {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));

    /** @var \Drupal\group\Entity\GroupInterface|null $group */
    $group = $route_match->getParameter('group');

    if ($group instanceof GroupInterface) {
      $breadcrumb->addCacheableDependency($group);

      if ($group->hasField('field_race') && !$group->get('field_race')->isEmpty()) {
        $race = $group->get('field_race')->entity;
        if ($race) {
          $breadcrumb->addLink(Link::createFromRoute($race->label(), 'entity.race.canonical', ['race' => $race->id()]));
          $breadcrumb->addCacheableDependency($race);
        }
      }

      $breadcrumb->addLink(Link::createFromRoute($group->label(), 'entity.group.canonical', ['group' => $group->id()]));
    }

    // The current page itself — unlinked, matching how every other page's
    // trailing crumb behaves on this site (easy_breadcrumb's own
    // EasyBreadcrumbBuilder::build() uses this identical
    // Link::createFromRoute($title, '<none>') pattern for the same
    // purpose: '<none>' is Drupal's dedicated "no destination" route,
    // which breadcrumb.html.twig (material_base) renders as plain text
    // rather than an <a> — see its `{% if item.url %}` check.
    $breadcrumb->addLink(Link::createFromRoute($this->t('Live Map'), '<none>'));

    $breadcrumb->addCacheContexts(['route']);

    return $breadcrumb;
  }

}
