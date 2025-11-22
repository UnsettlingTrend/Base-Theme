<?php

namespace Drupal\ut_utilities\Utility;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use \Drupal\Core\Entity\EntityStorageInterface;

class ReadSiteVersion {

  /**
  * The node storage instance.
  */
  protected EntityStorageInterface $nodeStorage;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * @throws \Exception
   */
  public function updateSiteVersion(): void {
    \Drupal::state()->set('environment_indicator.current_release',$this->getCurrentGitTags());
  }

  /**
   * Attempt to Retrieve Current Git Commit Tags in PHP.
   *
   * @return string
   */
  protected function getCurrentGitTags(): string {
    $results = [];
    exec('git describe --tags', $results);
    return implode($results);
  }
}
