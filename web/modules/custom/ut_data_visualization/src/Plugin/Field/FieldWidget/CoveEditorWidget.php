<?php

declare(strict_types=1);

namespace Drupal\ut_data_visualization\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Field widget that launches the CDC COVE editor in a modal dialog.
 *
 * Instead of exposing a raw JSON textarea, this widget presents an
 * "Open Editor" button. Clicking the button opens a full-screen modal
 * containing the COVE editor React application. When the user saves
 * inside the editor, the resulting JSON configuration is written back
 * into a hidden textarea so Drupal's form system captures it on submit.
 *
 * The COVE editor bundles are loaded from CDN via the `cove_editor`
 * library defined in ut_data_visualization.libraries.yml.
 */
#[FieldWidget(
  id: 'cove_editor',
  label: new TranslatableMarkup('COVE Editor (Modal)'),
  field_types: ['json'],
)]
class CoveEditorWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   *
   * Builds the form element for a single field delta.
   *
   * Renders:
   * - A hidden textarea that holds the raw JSON configuration string. This
   *   element is the actual form value submitted to Drupal.
   * - A status container showing whether a visualization is configured.
   * - An "Open COVE Editor" button that triggers the modal via JS.
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state,
  ): array {
    $value = $items[$delta]->value ?? '';

    // Generate a unique HTML ID so the JS behavior can target this instance.
    $wrapper_id = 'cove-editor-wrapper-' . $delta;

    $element['#type'] = 'container';
    $element['#attributes']['class'][] = 'cove-editor-widget';
    $element['#attributes']['id'] = $wrapper_id;

    // Hidden textarea that stores the JSON value. The COVE editor JS writes
    // the serialised config back into this element on save.
    $element['value'] = [
      '#type' => 'textarea',
      '#default_value' => $value,
      '#attributes' => [
        'class' => ['cove-editor-json-value', 'visually-hidden'],
        'data-cove-delta' => $delta,
      ],
      // No title — the widget label comes from the field instance config.
      '#title' => $this->t('JSON Configuration'),
      '#title_display' => 'invisible',
    ];

    // Status indicator showing whether a config has been saved.
    $has_config = !empty($value) && $value !== '{}';
    $element['status'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['cove-editor-status'],
        'data-cove-delta' => $delta,
      ],
    ];
    $element['status']['message'] = [
      '#markup' => $has_config
        ? '<span class="cove-editor-status__configured">✔ ' . $this->t('Visualization configured') . '</span>'
        : '<span class="cove-editor-status__empty">' . $this->t('No visualization configured yet.') . '</span>',
    ];

    // Preview container — shows a read-only rendering of the current config.
    $element['preview'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['cove-editor-preview'],
        'data-cove-preview-delta' => $delta,
      ],
    ];

    // Button to open the COVE editor modal.
    $element['open_editor'] = [
      '#type' => 'button',
      '#value' => $has_config ? $this->t('Edit Visualization') : $this->t('Create Visualization'),
      '#attributes' => [
        'class' => ['cove-editor-open-button', 'button', 'button--primary'],
        'data-cove-delta' => $delta,
        'type' => 'button',
      ],
      // Prevent this button from submitting the form.
      '#limit_validation_errors' => [],
      '#executes_submit_callback' => FALSE,
    ];

    // Attach the COVE editor library which contains the modal JS and CSS.
    $element['#attached']['library'][] = 'ut_data_visualization/cove_editor';
    // Also attach the renderer library for the in-widget preview.
    $element['#attached']['library'][] = 'ut_data_visualization/cove_renderer';

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * Extracts the JSON string from the submitted form values.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$value) {
      // The nested 'value' key comes from our textarea sub-element.
      if (is_array($value) && isset($value['value'])) {
        $value = ['value' => $value['value']];
      }
    }
    return $values;
  }

}

