<?php

/**
 * @file
 * Hooks provided by the UT Utilities module.
 */

/**
 * Define areas of interest available on user profiles.
 *
 * Each area appears as a checkbox on the user edit/register form. When a user
 * checks an area, all listed roles are granted; unchecking removes them (unless
 * another checked area also maps to the same role).
 *
 * @return array<string, array>
 *   Associative array keyed by a unique machine name. Each value is an array
 *   with the following keys:
 *   - label (string, required): Human-readable checkbox label.
 *   - roles (string[], required): Drupal role IDs to assign when checked.
 *   - description (string, optional): Help text shown beneath the checkbox.
 *   - weight (int, optional): Sort order. Lower values appear first. Default 0.
 *   - edit_permission (string, optional): If set, only users who have this
 *     permission may change this checkbox's value — for any account,
 *     including their own. Everyone else still sees the checkbox (and its
 *     current value), but it renders #disabled, and submitting a tampered
 *     value for it is rejected in form validation. Default: anyone who can
 *     access the form (self, or an admin editing another account) may
 *     change it.
 *
 * @see hook_ut_utilities_areas_of_interest_info_alter()
 */
function hook_ut_utilities_areas_of_interest_info(): array {
  return [
    'example_area' => [
      'label' => t('Example Area'),
      'description' => t('Grants access to example features.'),
      'roles' => ['example_role'],
      'weight' => 0,
    ],
  ];
}

/**
 * Alter area-of-interest definitions provided by other modules.
 *
 * @param array $areas
 *   The collected definitions from all hook_ut_utilities_areas_of_interest_info
 *   implementations, passed by reference.
 *
 * @see hook_ut_utilities_areas_of_interest_info()
 */
function hook_ut_utilities_areas_of_interest_info_alter(array &$areas): void {
  // Example: rename a label defined by another module.
  if (isset($areas['example_area'])) {
    $areas['example_area']['label'] = t('Renamed Area');
  }
}
