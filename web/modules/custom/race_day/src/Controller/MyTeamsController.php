<?php

declare(strict_types=1);

namespace Drupal\race_day\Controller;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Displays the current user's race teams and team requests.
 */
class MyTeamsController extends ControllerBase {

  /**
   * Builds the "My Teams" page.
   */
  public function page(): array {
    $build = [
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];

    $build['description'] = [
      '#markup' => '<p>' . $this->t("View the teams (past and present) that you're a member of, and any team requests you've made.") . '</p>',
    ];

    $build['my_teams'] = $this->buildViewsBlock('views_block:race_teams-block_2');
    $build['my_team_requests'] = $this->buildViewsBlock('views_block:race_team_requests-block_2');

    return $build;
  }

  /**
   * Renders a Views block by plugin ID with its title and contextual links,
   * the same as if it had been placed normally via Block Layout.
   */
  protected function buildViewsBlock(string $plugin_id): array {
    $block_manager = \Drupal::service('plugin.manager.block');
    /** @var \Drupal\Core\Block\BlockPluginInterface $plugin_block */
    $plugin_block = $block_manager->createInstance($plugin_id, [
      'label_display' => BlockPluginInterface::BLOCK_LABEL_VISIBLE,
    ]);

    $access = $plugin_block->access($this->currentUser(), TRUE);
    if ($access->isForbidden()) {
      return [
        '#cache' => [
          'contexts' => $access->getCacheContexts(),
          'tags' => $access->getCacheTags(),
          'max-age' => $access->getCacheMaxAge(),
        ],
      ];
    }

    return [
      '#theme' => 'block',
      '#attributes' => [],
      '#configuration' => $plugin_block->getConfiguration(),
      '#plugin_id' => $plugin_block->getPluginId(),
      '#base_plugin_id' => $plugin_block->getBaseId(),
      '#derivative_plugin_id' => $plugin_block->getDerivativeId(),
      'content' => $plugin_block->build(),
    ];
  }

}
