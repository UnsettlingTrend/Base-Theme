<?php

/**
 * @file
 * HTML template functions.
 */

/**
 * Implements hook_preprocess().
 *
 * @param array $variables
 *   Variables that get passed along to the theming template.
 */
function chris_preprocess(array &$variables) {
  if (!empty($variables['directory'])) {
    $variables['chris_image_path'] = '/' . $variables['directory'] . '/images';
  }
}

/**
 * Implements hook_preprocess_html().
 */
function chris_preprocess_html(&$vars, $hook) {
  // Add x-ua-compatible meta tag.
  $vars['page']['#attached']['html_head'][] = [
    [
      '#tag' => 'meta',
      '#attributes' => [
        'http-equiv' => 'x-ua-compatible',
        'content' => 'ie=edge',
      ],
    ],
    'x_ua_compatible',
  ];
}
