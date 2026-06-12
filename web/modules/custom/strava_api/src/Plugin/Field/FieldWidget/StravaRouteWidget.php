<?php
declare(strict_types=1);
namespace Drupal\strava_api\Plugin\Field\FieldWidget;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
/**
 * Widget for entering a Strava route ID with a live preview.
 */
#[FieldWidget(
  id: 'strava_route_widget',
  label: new TranslatableMarkup('Strava Route'),
  field_types: ['string'],
)]
class StravaRouteWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $value = $items[$delta]->value ?? '';
    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Strava Route ID'),
      '#description' => $this->t('Enter a Strava route ID (e.g. 123456789) or full URL (e.g. https://www.strava.com/routes/123456789). The numeric ID will be extracted automatically.'),
      '#default_value' => $value,
      '#maxlength' => 64,
      '#element_validate' => [[static::class, 'validateRouteId']],
    ];
    // Show a live preview if a route ID is already set.
    if (!empty($value)) {
      $route_id = static::extractRouteId($value);
      if ($route_id) {
        $element['preview'] = [
          '#type' => 'markup',
          '#markup' => '<div class="strava-route-widget__preview">'
            . $this->t('Preview on Strava:') . ' '
            . '<a href="https://www.strava.com/routes/' . htmlspecialchars($route_id) . '" target="_blank" rel="noopener noreferrer">'
            . 'https://www.strava.com/routes/' . htmlspecialchars($route_id)
            . '</a>'
            . '</div>',
        ];
      }
    }
    return $element;
  }
  /**
   * Validates and normalises the Strava route ID input.
   *
   * Accepts a numeric ID or a full Strava URL and extracts just the ID.
   */
  public static function validateRouteId(array &$element, FormStateInterface $form_state, array &$form): void {
    $value = trim($element['#value']);
    if (empty($value)) {
      return;
    }
    $route_id = static::extractRouteId($value);
    if (!$route_id) {
      $form_state->setError($element, t('Invalid Strava route. Enter a numeric route ID or a valid Strava route URL.'));
      return;
    }
    // Normalise to just the numeric ID.
    $form_state->setValueForElement($element, $route_id);
  }
  /**
   * Extracts a numeric Strava route ID from a URL or plain ID string.
   */
  public static function extractRouteId(string $input): ?string {
    $input = trim($input);
    // If it's already a pure numeric ID, return it.
    if (ctype_digit($input)) {
      return $input;
    }
    // Try to extract from a Strava URL like:
    // https://www.strava.com/routes/123456789
    // https://strava.com/routes/123456789/embed
    if (preg_match('#strava\.com/routes/(\d+)#i', $input, $matches)) {
      return $matches[1];
    }
    return NULL;
  }
}
