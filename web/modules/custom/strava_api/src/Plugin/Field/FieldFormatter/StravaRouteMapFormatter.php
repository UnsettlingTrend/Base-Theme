<?php
declare(strict_types=1);
namespace Drupal\strava_api\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
/**
 * Renders a Strava route as an interactive map from saved polyline data.
 */
#[FieldFormatter(
  id: 'strava_route_map',
  label: new TranslatableMarkup('Strava Route Map'),
  field_types: ['string'],
)]
class StravaRouteMapFormatter extends FormatterBase {
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $config = \Drupal::config('strava_api.settings');
    $map_height = $config->get('map_height') ?: '500px';
    foreach ($items as $delta => $item) {
      $route_id = trim($item->value ?? '');
      if (empty($route_id)) {
        $elements[$delta] = [
          '#markup' => '<div class="strava-route-map strava-route-map--error">'
            . $this->t('No Strava route configured.')
            . '</div>',
        ];
        continue;
      }
      if (!ctype_digit($route_id)) {
        $elements[$delta] = [
          '#markup' => '<div class="strava-route-map strava-route-map--error">'
            . $this->t('Invalid Strava route ID.')
            . '</div>',
        ];
        continue;
      }

      $paragraph = $items->getEntity();
      $polyline = '';
      $summary_polyline = '';

      if ($paragraph->hasField('field_strava_polyline')) {
        $polyline = (string) ($paragraph->get('field_strava_polyline')->value ?? '');
      }
      if ($paragraph->hasField('field_strava_summary_polyline')) {
        $summary_polyline = (string) ($paragraph->get('field_strava_summary_polyline')->value ?? '');
      }
      if (!$polyline && !$summary_polyline && $paragraph->hasField('field_route_data')) {
        $polyline = (string) ($paragraph->get('field_route_data')->value ?? '');
      }

      $unique_id = 'strava-route-' . $paragraph->id() . '-' . $delta;
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['strava-route-map'],
          'id' => $unique_id,
          'data-strava-route-id' => $route_id,
          'data-strava-route-url' => 'https://www.strava.com/routes/' . $route_id,
          'data-strava-polyline' => Html::escape($polyline),
          'data-strava-summary-polyline' => Html::escape($summary_polyline),
          'data-strava-map-height' => $map_height,
        ],
        '#value' => '<div class="strava-route-map--loading">'
          . $this->t('Loading Strava route map…')
          . '</div>',
        '#attached' => [
          'library' => ['strava_api/strava_route_map'],
        ],
      ];
    }
    return $elements;
  }
}
