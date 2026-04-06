<?php

namespace Drupal\recipes\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\recipes\Service\FractionService;

class RecipesHooks {

  public function __construct(
    protected FractionService $fractionService,
  ) {}

  #[Hook('preprocess_field')]
  public function preprocessField(&$variables): void {
    $field_name = $variables['field_name'];
    if($field_name == 'field_number' || $field_name == 'field_weight' || $field_name == 'field_volume') {
      $value = $variables['items'][0]['content']['#markup'];

      // If the value starts with a number, filter the number
      if ($value && preg_match('/^(\d+\.?\d*)(.*)/s', $value, $matches)) {
        $number = $matches[1];
        $remainder = $matches[2];
        $variables['items'][0]['content']['#markup'] = $this->fractionService->toFraction((float) $number) . $remainder;
      }

    }
  }

}
