<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * A single-button form, embedded on a user's profile page (see
 * ut_tracking_user_view()), letting the CURRENTLY LOGGED IN user
 * grant/revoke the PROFILE user access to the logged-in user's own
 * current location — i.e. this always acts on the viewer's own Location
 * Access group, regardless of whose profile it's rendered on.
 *
 * "Grant Location Access" (mdc-button--raised, darker/filled) shows when
 * the profile user isn't yet a member of the viewer's group; "Revoke
 * Location Access" (mdc-button--outlined, lighter) shows when they are.
 * Membership is re-checked in submitForm() rather than trusted from the
 * built form, so a stale page (e.g. two tabs) can't toggle the wrong way.
 */
class LocationAccessToggleForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ut_tracking_location_access_toggle_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?UserInterface $profile_user = NULL): array {
    if (!$profile_user) {
      return $form;
    }

    $viewer = User::load($this->currentUser()->id());
    if (!$viewer) {
      return $form;
    }

    $group = _ut_tracking_get_location_access_group($viewer);
    $is_member = $group && $group->getMember($profile_user);

    $form['target_uid'] = [
      '#type' => 'value',
      '#value' => $profile_user->id(),
    ];
    $form['toggle'] = [
      '#type' => 'submit',
      '#value' => $is_member ? $this->t('Revoke Location Access') : $this->t('Grant Location Access'),
      '#attributes' => [
        'class' => $is_member
          ? ['mdc-button', 'mdc-button--outlined']
          : ['mdc-button', 'mdc-button--raised'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $target_user = User::load($form_state->getValue('target_uid'));
    $viewer = User::load($this->currentUser()->id());
    if (!$target_user || !$viewer) {
      return;
    }

    $group = _ut_tracking_ensure_location_access_group($viewer);
    if ($group->getMember($target_user)) {
      $group->removeMember($target_user);
      $this->messenger()->addStatus($this->t('%name can no longer see your location.', [
        '%name' => $target_user->getDisplayName(),
      ]));
    }
    else {
      $group->addMember($target_user);
      $this->messenger()->addStatus($this->t('%name can now see your location.', [
        '%name' => $target_user->getDisplayName(),
      ]));
    }

    $form_state->setRedirect('entity.user.canonical', ['user' => $target_user->id()]);
  }

}
