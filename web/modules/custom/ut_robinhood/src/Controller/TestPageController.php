<?php
namespace Drupal\ut_robinhood\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides route responses for the Example module.
 */
class TestPageController extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function testPage(): array {
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('ut_robinhood')->getPath();



  //  require('');
    $command = escapeshellcmd('python3 ' . $module_path . '/python/main.py');
    dpm($command, '$command');
    $output = shell_exec($command);
    dpm($output, '$output1');
    $output = shell_exec('pwd');
    dpm($output, '$output2');
    return [
      '#markup' => 'This is a test page',
    ];
  }

}
