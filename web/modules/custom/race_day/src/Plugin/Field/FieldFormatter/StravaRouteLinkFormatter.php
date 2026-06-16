<?php

declare(strict_types=1);

namespace Drupal\race_day\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders a race_leg's Strava route field as a link to strava.com.
 *
 * Reads field_strava_route_id from the referenced strava_route paragraph.
 * Accepts either a bare numeric ID (123456789) or a full Strava URL.
 */
#[FieldFormatter(
  id: 'strava_route_link',
  label: new TranslatableMarkup('Strava route link'),
  field_types: ['entity_reference_revisions'],
)]
class StravaRouteLinkFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    foreach ($items as $delta => $item) {
      $paragraph = $item->entity;
      if (!$paragraph || $paragraph->bundle() !== 'strava_route') {
        continue;
      }

      $raw = $paragraph->get('field_strava_route_id')->value;
      if (empty($raw)) {
        continue;
      }

      if (filter_var($raw, FILTER_VALIDATE_URL)) {
        $url = $raw;
      }
      else {
        $url = 'https://www.strava.com/routes/' . $raw;
      }

      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $this->t('View on Strava'),
        '#url' => \Drupal\Core\Url::fromUri($url),
        '#attributes' => ['target' => '_blank', 'rel' => 'noopener noreferrer'],
      ];
    }

    return $elements;
  }

}
