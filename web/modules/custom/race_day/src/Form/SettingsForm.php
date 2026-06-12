<?php

declare(strict_types=1);

namespace Drupal\race_day\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Race Day module settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['race_day.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'race_day_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('race_day.settings');

    $form['location_update_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Location update interval (seconds)'),
      '#description' => $this->t('How frequently runners should submit GPS location updates during a race.'),
      '#default_value' => $config->get('location_update_interval') ?: 10,
      '#min' => 1,
      '#max' => 300,
    ];

    $form['map_provider'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map tile provider URL'),
      '#description' => $this->t('Tile URL template for the race map. Use {z}, {x}, {y} placeholders.'),
      '#default_value' => $config->get('map_provider') ?: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
      '#maxlength' => 512,
    ];

    $form['allow_public_registration'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow public team registration'),
      '#description' => $this->t('If checked, authenticated users can create teams without admin approval.'),
      '#default_value' => $config->get('allow_public_registration') ?: FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('race_day.settings')
      ->set('location_update_interval', $form_state->getValue('location_update_interval'))
      ->set('map_provider', $form_state->getValue('map_provider'))
      ->set('allow_public_registration', $form_state->getValue('allow_public_registration'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

