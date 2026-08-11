<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Rejects GPS location API requests that did not arrive over HTTPS.
 *
 * The mobile app authenticates with HTTP Basic Auth on every request, which
 * sends the account's credentials in the clear unless the transport itself
 * is encrypted — this closes that off at the application layer as a
 * defense-in-depth backstop alongside TLS termination at the edge.
 */
class SecureRequestAccessCheck {

  /**
   * Access check callback for ut_tracking.report_location.
   *
   * Relies on Request::isSecure(), which itself honors Drupal's
   * reverse-proxy trust settings ($settings['reverse_proxy_*'] in
   * settings.php) when the site sits behind a TLS-terminating proxy or
   * load balancer — that must be configured correctly for this check to
   * reflect the real client connection in that kind of deployment.
   */
  public function access(Request $request): AccessResultInterface {
    return AccessResult::allowedIf($request->isSecure())->setCacheMaxAge(0);
  }

}
