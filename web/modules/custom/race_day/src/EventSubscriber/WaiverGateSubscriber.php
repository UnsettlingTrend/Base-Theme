<?php

namespace Drupal\race_day\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects users to the participant waiver before accessing gated race routes.
 */
class WaiverGateSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 20],
    ];
  }

  public function onRequest(RequestEvent $event): void {
    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    $race = NULL;

    if ($route === 'entity.group.add_form') {
      if ($request->attributes->get('group_type')?->id() !== 'race_team') {
        return;
      }
      $race_id = (int) $request->query->get('race_id');
      if (!$race_id) {
        return;
      }
      $race = $this->entityTypeManager->getStorage('race')->load($race_id);
    }
    elseif ($route === 'race_day.runner_team_request') {
      $race = $request->attributes->get('race');
    }

    if (!$race || !$this->userNeedsWaiver($race)) {
      return;
    }

    $redirect_url = Url::fromRoute('race_day.waiver_modal', ['race' => $race->id()], [
      'query' => ['return_route' => $route],
    ])->toString();
    $event->setResponse(new RedirectResponse($redirect_url));
  }

  private function userNeedsWaiver($race): bool {
    if ($race->get('field_waiver')->isEmpty()) {
      return FALSE;
    }
    if ($this->currentUser->isAnonymous()) {
      return TRUE;
    }
    $storage = $this->entityTypeManager->getStorage('webform_submission');
    foreach ($race->get('field_waiver') as $item) {
      $webform = $item->entity;
      if (!$webform) {
        continue;
      }
      $existing = $storage->loadByProperties([
        'webform_id' => $webform->id(),
        'uid' => $this->currentUser->id(),
      ]);
      if (empty($existing)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
