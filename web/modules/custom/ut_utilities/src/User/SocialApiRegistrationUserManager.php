<?php

namespace Drupal\ut_utilities\User;

use Drupal\social_auth\User\UserManager;

/**
 * Overrides Social Auth's registration/status gates when configured.
 *
 * @see \Drupal\ut_utilities\UtUtilitiesServiceProvider
 */
class SocialApiRegistrationUserManager extends UserManager {

  /**
   * {@inheritdoc}
   */
  protected function isRegistrationDisabled(): bool {
    if ($this->configFactory->get('ut_utilities.settings')->get('allow_social_api_registration')) {
      return FALSE;
    }
    return parent::isRegistrationDisabled();
  }

  /**
   * {@inheritdoc}
   */
  protected function getNewUserStatus(): int {
    if ($this->configFactory->get('ut_utilities.settings')->get('allow_social_api_registration')) {
      return 1;
    }
    return parent::getNewUserStatus();
  }

}
