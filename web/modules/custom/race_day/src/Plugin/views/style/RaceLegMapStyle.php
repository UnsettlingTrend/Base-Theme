<?php

declare(strict_types=1);

namespace Drupal\race_day\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Renders all race leg polylines on a single shared Leaflet map.
 *
 * @ViewsStyle(
 *   id = "race_leg_map",
 *   title = @Translation("Race Leg Map"),
 *   help = @Translation("Renders all race leg polylines on a single Leaflet map."),
 *   theme = "views_view_unformatted",
 *   display_types = {"normal"}
 * )
 */
class RaceLegMapStyle extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['hide_leg_legend'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    $form['hide_leg_legend'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide leg legend'),
      '#description' => $this->t('When checked, the legend listing each leg and its colour is hidden beneath the map.'),
      '#default_value' => $this->options['hide_leg_legend'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $legs = [];

    foreach ($this->view->result as $row) {
      /** @var \Drupal\race_day\Entity\RaceLeg|null $entity */
      $entity = $row->_entity ?? NULL;
      if (!$entity || !$entity->hasField('route')) {
        continue;
      }

      $route_field = $entity->get('route');
      if ($route_field->isEmpty()) {
        continue;
      }

      /** @var \Drupal\paragraphs\Entity\Paragraph|null $paragraph */
      $paragraph = $route_field->entity;
      if (!$paragraph) {
        continue;
      }

      $polyline = '';
      if ($paragraph->hasField('field_strava_polyline') && !$paragraph->get('field_strava_polyline')->isEmpty()) {
        $polyline = (string) $paragraph->get('field_strava_polyline')->value;
      }
      elseif ($paragraph->hasField('field_strava_summary_polyline') && !$paragraph->get('field_strava_summary_polyline')->isEmpty()) {
        $polyline = (string) $paragraph->get('field_strava_summary_polyline')->value;
      }

      if (empty($polyline)) {
        continue;
      }

      $legs[] = [
        'label' => $entity->label(),
        'leg_number' => (int) ($entity->get('leg_number')->value ?? 0),
        'distance' => (string) ($entity->getDistance() ?? ''),
        'polyline' => $polyline,
      ];
    }

    $attributes = [
      'class' => ['race-leg-map'],
      'data-legs' => json_encode($legs),
    ];

    if (!empty($this->options['hide_leg_legend'])) {
      $attributes['data-hide-legend'] = 'true';
    }

    return [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => $attributes,
      '#value' => '<div class="race-leg-map__canvas"></div>',
      '#attached' => [
        'library' => ['race_day/race_leg_map'],
      ],
    ];
  }

}
