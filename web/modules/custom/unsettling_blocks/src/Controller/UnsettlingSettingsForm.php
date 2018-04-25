<?php
/**
 * Created by PhpStorm.
 * User: cferagotti
 * Date: 2/16/18
 * Time: 9:49 PM
 */

namespace Drupal\unsettling_blocks\Controller;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class UnsettlingSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unsettling_admin_settings';
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return [
      'unsettling.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('unsettling.settings');

    $api_link = 'https://support.gdax.com/customer/en/portal/articles/2425383-how-can-i-create-an-api-key-for-gdax-';

    $form['gdax_api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('GDAX API Key'),
      '#description' => t('API key for accessing <a href="@API_LINK">GDAX API</a>; you\'ll need \'view\' access.', array('@API_LINK' => @api_link)),
      '#default_value' => $config->get('gdax_api_key'),
    );
    $form['gdax_api_secret'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('GDAX API Secret'),
      '#description' => t('API Secret for accessing <a href="@API_LINK">GDAX API</a>; you\'ll need \'view\' access.', array('@API_LINK' => @api_link)),
      '#default_value' => $config->get('gdax_api_secret'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->configFactory->getEditable('unsettling.settings')
      // Set the submitted configuration setting
      ->set('gdax_api_key', $form_state->getValue('gdax_api_key'))
      ->set('gdax_api_secret', $form_state->getValue('gdax_api_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}




