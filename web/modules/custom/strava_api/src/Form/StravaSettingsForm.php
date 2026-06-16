<?php
declare(strict_types=1);
namespace Drupal\strava_api\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
/**
 * Strava API module settings form.
 */
class StravaSettingsForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['strava_api.settings'];
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'strava_api_settings_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('strava_api.settings');
    $form['credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Strava API Credentials'),
      '#open' => TRUE,
    ];
    $form['credentials']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('Your Strava API application Client ID. Get one at https://www.strava.com/settings/api'),
      '#default_value' => $config->get('client_id') ?: '',
      '#maxlength' => 64,
    ];
    $form['credentials']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('Your Strava API application Client Secret.'),
      '#default_value' => $config->get('client_secret') ?: '',
      '#maxlength' => 128,
    ];
    $form['credentials']['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('OAuth access token. Refreshed automatically when expired if a Refresh Token is set.'),
      '#default_value' => $config->get('access_token') ?: '',
      '#maxlength' => 255,
    ];
    $form['credentials']['refresh_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Refresh Token'),
      '#description' => $this->t('Long-lived OAuth refresh token used to obtain new access tokens automatically.'),
      '#default_value' => $config->get('refresh_token') ?: '',
      '#maxlength' => 255,
    ];
    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('Display Settings'),
      '#open' => TRUE,
    ];
    $form['display']['map_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default map height'),
      '#description' => $this->t('CSS height value for the embedded route map, e.g. 500px, 60vh.'),
      '#default_value' => $config->get('map_height') ?: '500px',
      '#maxlength' => 16,
    ];
    $form['display']['map_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Map style'),
      '#options' => [
        'outdoor' => $this->t('Outdoor'),
        'satellite' => $this->t('Satellite'),
        'standard' => $this->t('Standard'),
      ],
      '#default_value' => $config->get('map_style') ?: 'outdoor',
    ];
    $form['display']['show_elevation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show elevation profile'),
      '#description' => $this->t('Display the elevation chart below the route map.'),
      '#default_value' => $config->get('show_elevation') ?? TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('strava_api.settings')
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->set('access_token', $form_state->getValue('access_token'))
      ->set('refresh_token', $form_state->getValue('refresh_token'))
      ->set('map_height', $form_state->getValue('map_height'))
      ->set('map_style', $form_state->getValue('map_style'))
      ->set('show_elevation', (bool) $form_state->getValue('show_elevation'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
