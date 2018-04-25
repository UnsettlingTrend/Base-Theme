<?php

namespace Drupal\unsettling_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'CryptoCurrencyBlock' block.
 *
 * @Block(
 *  id = "cryptocurrency_block",
 *  admin_label = @Translation("CryptoCurrency Block"),
 * )
 */
class CryptoCurrencyBlock extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['cryptocurrency_block']['#markup'] .= '<span class="price-integers">100</span><span class="price-decimals">99</span>';
    $build['#attached']['library'][] = 'unsettling_blocks/unsettling.cryptocurrency';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['cryptocurrency_block_currency'] = array(
      '#type' => 'select',
      '#title' => $this->t('Currency Type'),
      '#options' => [
        'ltc' => $this
          ->t('LiteCoin (LTC)'),
      ],
      '#description' => $this->t('Choice the currency.'),
      '#default_value' => isset($config['cryptocurrency_block_currency']) ? $config['cryptocurrency_block_currency'] : '',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['cryptocurrency_block_currency'] = $values['cryptocurrency_block_currency'];
  }

}
