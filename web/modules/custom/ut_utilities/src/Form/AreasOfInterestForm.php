<?php

namespace Drupal\ut_utilities\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;

/**
 * Form for managing a user's areas of interest.
 */
class AreasOfInterestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ut_utilities_areas_of_interest_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $user = NULL): array {
    $form_state->set('user', $user);

    $areas = ut_utilities_get_areas_of_interest();

    $current = [];
    if ($user?->id()) {
      $current = \Drupal::service('user.data')
        ->get('ut_utilities', $user->id(), 'areas_of_interest') ?? [];
    }

    $options = [];
    foreach ($areas as $key => $info) {
      $options[$key] = $info['label'];
    }

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Select your preferred areas of interest. This will determine your access to certain areas of the site. You can always add/remove an option, but remember that any remaining content will still persist, you\'ll just lose access to it as long as you remain uninterested. Any options you can\'t modify can likely only be adjusted by site administrators.'),
      '#weight' => -10,
    ];

    $form['areas_of_interest'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Areas of Interest'),
      '#options' => $options,
      '#default_value' => $current,
      // Descriptions are per-option and must be set after the element is
      // expanded, so we use #after_build.
      '#after_build' => [
        [static::class, 'addOptionDescriptions'],
      ],
      '#areas' => $areas,
    ];
    static::applyEditRestrictions($areas, $form['areas_of_interest']);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * #after_build callback: attaches per-option descriptions to each checkbox.
   *
   * Shared by both AreasOfInterestForm (the profile tab) and
   * ut_utilities_form_user_register_form_alter() (the registration form).
   * Descriptions are cosmetic (rendering-only), so setting them here, after
   * Checkboxes::processCheckboxes() has expanded #options into per-key
   * sub-elements, is fine. #disabled is NOT set here — see
   * applyEditRestrictions() for why that has to happen earlier.
   */
  public static function addOptionDescriptions(array $element, FormStateInterface $form_state): array {
    $areas = $element['#areas'];
    foreach ($areas as $key => $info) {
      if (isset($element[$key]) && !empty($info['description'])) {
        $element[$key]['#description'] = $info['description'];
      }
    }
    return $element;
  }

  /**
   * Disables checkboxes for areas the current user has no edit_permission for.
   *
   * Must be called on the checkboxes element BEFORE it enters Drupal's form
   * build pipeline (i.e. right after constructing $form['areas_of_interest'],
   * not from an #after_build callback) — Checkboxes::valueCallback() and
   * ::processCheckboxes() (web/core/lib/Drupal/Core/Render/Element/
   * Checkboxes.php) both check $element[$key]['#disabled'] while resolving
   * which submitted values to accept, and that resolution happens before
   * #after_build ever runs. Setting #disabled that late (as an #after_build
   * callback previously did here) has no effect at all by the time it
   * fires: the submitted value was already accepted, and the HTML
   * `disabled` attribute was already omitted — which is exactly why that
   * approach silently didn't work.
   *
   * @param array<string, array> $areas
   *   Area definitions from ut_utilities_get_areas_of_interest().
   * @param array $element
   *   The checkboxes element (i.e. $form['areas_of_interest']), by reference.
   */
  public static function applyEditRestrictions(array $areas, array &$element): void {
    $current_user = \Drupal::currentUser();
    foreach ($areas as $key => $info) {
      if (!empty($info['edit_permission']) && !$current_user->hasPermission($info['edit_permission'])) {
        $element[$key]['#disabled'] = TRUE;
      }
    }
  }

  /**
   * Shared #validate callback: rejects tampering with permission-restricted
   * checkboxes.
   *
   * applyEditRestrictions() above renders a restricted checkbox #disabled
   * early enough that Drupal's Form API itself now refuses the submitted
   * value for it (falling back to #default_value instead — see
   * Checkboxes::valueCallback()). This is a second, explicit, auditable
   * check against anything that might bypass that (a hand-crafted request
   * that skips the normal render/build cycle entirely), comparing the
   * submitted value against the same baseline the disabling check used
   * rather than trusting that mechanism alone. Used by both
   * AreasOfInterestForm (as #validate via validateForm()) and
   * ut_utilities_form_user_register_form_alter() (appended directly to
   * $form['#validate'], since that form has no form object of its own).
   */
  public static function validateRestrictedAreas(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['areas_of_interest'])) {
      return;
    }

    $element = $form['areas_of_interest'];
    $areas = $element['#areas'] ?? [];
    // #default_value for `checkboxes` is a plain list of checked keys (e.g.
    // ['content_creation', 'running']), not a $key => $key map — so
    // membership must be checked with in_array(), not array access.
    $baseline = $element['#default_value'] ?? [];
    $submitted = $form_state->getValue('areas_of_interest') ?? [];
    $current_user = \Drupal::currentUser();

    foreach ($areas as $key => $info) {
      if (empty($info['edit_permission']) || $current_user->hasPermission($info['edit_permission'])) {
        continue;
      }
      $was_checked = in_array($key, $baseline, TRUE);
      $is_checked = !empty($submitted[$key]);
      if ($was_checked !== $is_checked) {
        $form_state->setErrorByName('areas_of_interest][' . $key, t('You do not have permission to change the %label area of interest.', [
          '%label' => $info['label'],
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    static::validateRestrictedAreas($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $areas = ut_utilities_get_areas_of_interest();

    /** @var \Drupal\user\UserInterface $account */
    $account = $form_state->get('user');
    $uid = $account->id();

    $user_data = \Drupal::service('user.data');
    $old_selections = $user_data->get('ut_utilities', $uid, 'areas_of_interest') ?? [];

    $raw = $form_state->getValue('areas_of_interest') ?? [];
    $new_selections = array_values(array_keys(array_filter($raw)));

    $user_data->set('ut_utilities', $uid, 'areas_of_interest', $new_selections);

    $added   = array_diff($new_selections, $old_selections);
    $removed = array_diff($old_selections, $new_selections);

    if (empty($added) && empty($removed)) {
      $this->messenger()->addStatus($this->t('Your areas of interest have been saved.'));
      return;
    }

    $roles_to_keep = [];
    foreach ($new_selections as $key) {
      foreach ($areas[$key]['roles'] ?? [] as $role_id) {
        $roles_to_keep[$role_id] = TRUE;
      }
    }

    $changed = FALSE;

    foreach ($added as $key) {
      foreach ($areas[$key]['roles'] ?? [] as $role_id) {
        if (!$account->hasRole($role_id)) {
          $account->addRole($role_id);
          $changed = TRUE;
        }
      }
    }

    foreach ($removed as $key) {
      foreach ($areas[$key]['roles'] ?? [] as $role_id) {
        if (empty($roles_to_keep[$role_id]) && $account->hasRole($role_id)) {
          $account->removeRole($role_id);
          $changed = TRUE;
        }
      }
    }

    if ($changed) {
      $account->save();
    }

    $this->messenger()->addStatus($this->t('Your areas of interest have been saved.'));
  }

}
