<?php

namespace Drupal\recipes\TwigExtension;

use Drupal\recipes\Service\FractionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FractionFilter extends AbstractExtension {

  public function __construct(
    protected FractionService $fractionService,
  ) {}

  public function getFilters() {
    return [
      new TwigFilter('fraction', [$this->fractionService, 'toFraction']),
    ];
  }

}
