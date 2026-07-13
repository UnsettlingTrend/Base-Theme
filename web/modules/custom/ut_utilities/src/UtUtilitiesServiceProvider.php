<?php

namespace Drupal\ut_utilities;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\ut_utilities\User\SocialApiRegistrationUserAuthenticator;
use Drupal\ut_utilities\User\SocialApiRegistrationUserManager;

/**
 * Swaps in Social Auth service overrides for the "allow Social API
 * registration" setting.
 *
 * Both social_auth.user_manager and social_auth.user_authenticator use
 * SettingsTrait independently (a trait, not shared inheritance), so the
 * registration/approval gates it defines have to be overridden in both
 * places to take effect everywhere they're checked.
 */
class UtUtilitiesServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('social_auth.user_manager')) {
      $container->getDefinition('social_auth.user_manager')->setClass(SocialApiRegistrationUserManager::class);
    }
    if ($container->hasDefinition('social_auth.user_authenticator')) {
      $container->getDefinition('social_auth.user_authenticator')->setClass(SocialApiRegistrationUserAuthenticator::class);
    }
  }

}
