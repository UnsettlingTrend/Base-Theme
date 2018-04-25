<?php
/**
 * Created by PhpStorm.
 * User: cferagotti
 * Date: 2/17/18
 * Time: 12:17 AM
 */

namespace Drupal\unsettling_blocks\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\AjaxResponse;

class CryptoCurrencyUpdateCommand implements CommandInterface {

  protected $form;

  public function __construct($form)
  {
    $this->form = $form;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render()
  {
    return [
      'command' => 'CryptoCurrencyUpdateCommand',
      'form' => $this->form,
    ];
  }

  public function getCryptoCurrencyBlockHtml($webformid) {
    $response = new Ajaxresponse();

    $webform = Webform::load($webformid);
    $renderable = $this->entityTypeManager()->getViewBuilder('webform')->view($webform);

    $rendered_form = \Drupal::service('renderer')->renderRoot($renderable);
    $response->setAttachments($renderable['#attached']);

    $response->addCommand(new CryptoCurrencyUpdateCommand($rendered_form));

    return $response;
  }
}