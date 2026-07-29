<?php

declare(strict_types=1);

namespace Drupal\race_day\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles AJAX updates to a race team member's own Pace field.
 */
class MemberPaceController extends ControllerBase {

  public function __construct(protected RendererInterface $renderer) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('renderer'));
  }

  public function update(Request $request): AjaxResponse|JsonResponse {
    $relationship_id = (int) $request->request->get('relationship_id');
    $pace = $request->request->get('pace');

    if (!$relationship_id || $pace === NULL) {
      return new JsonResponse(['error' => 'Missing parameters.'], 400);
    }

    $relationship = $this->entityTypeManager()
      ->getStorage('group_relationship')
      ->load($relationship_id);

    if (!$relationship || !$relationship->hasField('field_pace')) {
      return new JsonResponse(['error' => 'Membership not found.'], 404);
    }

    // Only the member themselves may change their own pace.
    if ((int) $relationship->get('entity_id')->target_id !== (int) $this->currentUser()->id()) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
    }

    // Validate the value against the webform's own option list, same as
    // RequestStatusController does for request_status.
    $webform = $this->entityTypeManager()->getStorage('webform')->load('race_day_runner_team_request');
    $element = $webform ? $webform->getElement('expected_pace') : NULL;
    $allowed = array_keys($element['#options'] ?? []);
    if (!in_array($pace, $allowed, TRUE)) {
      return new JsonResponse(['error' => 'Invalid pace value.'], 400);
    }

    $relationship->set('field_pace', $pace);
    $relationship->save();

    $response = new AjaxResponse();

    $group = $relationship->getGroup();
    if (!$group) {
      // No group to re-render against — return an empty command list so the
      // JS's ajax.success() still has an array to iterate.
      return $response;
    }

    // Re-render the Runner Assignments field, same wrapper/approach as
    // RequestStatusController::update().
    $group_fresh = $this->entityTypeManager()->getStorage('group')->loadUnchanged($group->id());
    $legs_build = $group_fresh->get('field_runner_legs')->view([
      'type' => 'entity_reference_revisions_entity_view',
      'label' => 'above',
      'settings' => ['view_mode' => 'default'],
      'weight' => 0,
    ]);
    $legs_html = (string) $this->renderer->renderInIsolation($legs_build);
    $response->addCommand(new ReplaceCommand('#runner-assignments-' . $group->id(), $legs_html));

    return $response;
  }

}
