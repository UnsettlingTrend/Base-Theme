<?php

namespace Drupal\recipes\Service;

class FractionService {

  public function toFraction($value) {
    // Maps decimal portions to their closest single-character fraction.
    $fractions = [
      '0.25' => '¼',
      '0.5'  => '½',
      '0.75' => '¾',
      '0.33' => '⅓',
      '0.67' => '⅔',
      '0.2'  => '⅕',
      '0.4'  => '⅖',
      '0.6'  => '⅗',
      '0.8'  => '⅘',
      '0.17' => '⅙',
      '0.83' => '⅚',
      '0.125' => '⅛',
      '0.375' => '⅜',
      '0.625' => '⅝',
      '0.875' => '⅞',
    ];

    $whole = (int) floor($value);
    // Round for stable map lookups from floating-point input.
    $decimal = round($value - $whole, 3);

    // If no glyph exists, keep the decimal part as a readable fallback.
    $fractionChar = $fractions[(string) $decimal] ?? ($decimal > 0 ? $decimal : '');

    if ($whole > 0 && $fractionChar !== '') {
      return $whole . $fractionChar;
    }
    elseif ($whole > 0) {
      return $whole;
    }
    else {
      return $fractionChar ?: $value;
    }
  }

}
