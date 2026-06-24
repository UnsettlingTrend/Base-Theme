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
      '#value' => $this->t('Select your preferred areas of interest. This will determine your access to certain areas of the site. You can always add/remove an option, but remember that any remaining content will still persist, you\'ll just lose access to it as long as you remain uninterested.'),
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
   */
  public static function addOptionDescriptions(array $element, FormStateInterface $form_state): array {
    $areas = $element['#areas'];
    foreach ($areas as $key => $info) {
      if (!empty($info['description']) && isset($element[$key])) {
        $element[$key]['#description'] = $info['description'];
      }
    }
    return $element;
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
