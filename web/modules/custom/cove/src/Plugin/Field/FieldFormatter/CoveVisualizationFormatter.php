<?php

declare(strict_types=1);

namespace Drupal\cove\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Field formatter that renders a CDC COVE visualization from stored JSON.
 *
 * For each field delta, this formatter outputs a container <div> with:
 * - A `data-cove-config` attribute containing the JSON configuration string.
 * - A unique `data-cove-id` attribute for targeting by the renderer JS.
 *
 * The `cove_renderer` JS library (attached automatically) scans for these
 * elements on page load and mounts the appropriate COVE React component
 * (chart, map, dashboard, etc.) based on the `type` key in the JSON config.
 *
 * If the JSON value is empty or not valid, a placeholder message is rendered
 * instead to avoid broken visualizations on the front end.
 */
#[FieldFormatter(
  id: 'cove_visualization',
  label: new TranslatableMarkup('COVE Visualization'),
  field_types: ['json'],
)]
class CoveVisualizationFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   * Builds render arrays for each field delta.
   *
   * Each delta produces a container div that the cove-renderer.js Drupal
   * behavior will pick up and hydrate with the appropriate COVE React
   * component (CdcChart, CdcMap, CdcDashboard, etc.).
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $json_value = $item->value ?? '';

      // Skip empty or trivially empty JSON values.
      if (empty($json_value) || $json_value === '{}' || $json_value === '[]') {
        $elements[$delta] = [
          '#markup' => '<div class="cove-visualization cove-visualization--empty">'
            . $this->t('No visualization data available.')
            . '</div>',
        ];
        continue;
      }

      // Validate that the value is parseable JSON before embedding it.
      $decoded = json_decode($json_value, TRUE);
      if ($decoded === NULL && json_last_error() !== JSON_ERROR_NONE) {
        $elements[$delta] = [
          '#markup' => '<div class="cove-visualization cove-visualization--error">'
            . $this->t('Invalid visualization configuration.')
            . '</div>',
        ];
        continue;
      }

      // Generate a unique ID for this visualization instance so the JS
      // renderer can target it precisely, even with multiple visualizations
      // on the same page.
      $unique_id = 'cove-viz-' . $items->getEntity()->id() . '-' . $delta;

      $elements[$delta] = [
        // The outer container that the COVE renderer mounts into.
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'class' => ['cove-visualization'],
          'data-cove-config' => $json_value,
          'data-cove-id' => $unique_id,
        ],
        // An initial loading indicator shown until React hydrates.
        '#value' => '<div class="cove-visualization__loading">'
          . $this->t('Loading visualization…')
          . '</div>',
        '#attached' => [
          'library' => ['cove/cove_renderer'],
        ],
      ];
    }

    return $elements;
  }

}

