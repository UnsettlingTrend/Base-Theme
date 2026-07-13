<?php

namespace Drupal\ut_utilities\User;

use Drupal\social_auth\User\UserAuthenticator;

/**
 * Overrides Social Auth's registration/approval gates when configured.
 *
 * @see \Drupal\ut_utilities\UtUtilitiesServiceProvider
 */
class SocialApiRegistrationUserAuthenticator extends UserAuthenticator {

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
  protected function isApprovalRequired(): bool {
    if ($this->configFactory->get('ut_utilities.settings')->get('allow_social_api_registration')) {
      return FALSE;
    }
    return parent::isApprovalRequired();
  }

}
