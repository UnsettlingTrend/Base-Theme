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

    // Save the current working directory to return to later.
    $cwd = getcwd();
    // Change to the python directory
    chdir($module_path . '/python/');

    $command = escapeshellcmd('python3 ' . 'main.py');
    $output = shell_exec($command);
    dpm($output, '$output1');

    // Return to the current working directory before this.
    chdir($cwd);
    return [
      '#markup' => 'This is a test page',
    ];
  }

}
