<?php

namespace Drupal\race_day\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\group\Entity\GroupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles AJAX runner assignment for race team leg assignments.
 */
class AssignRunnerController extends ControllerBase {

  public function __construct(protected RendererInterface $renderer) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('renderer'));
  }

  public function assign(Request $request, GroupInterface $group): AjaxResponse|JsonResponse {
    $token = $request->request->get('csrf_token');
    if (!\Drupal::csrfToken()->validate($token, 'assign-runner-' . $group->id())) {
      return new JsonResponse(['error' => 'Invalid token.'], 403);
    }

    $paragraph_id = (int) $request->request->get('paragraph_id');
    $user_id = (int) $request->request->get('user_id');

    $current_user = $this->currentUser();
    $membership = $group->getMember($current_user);

    if (!$membership) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
    }

    $is_captain_or_admin = FALSE;
    foreach ($membership->getRoles() as $role) {
      if (in_array($role->id(), ['race_team-admin', 'race_team-team_captain'])) {
        $is_captain_or_admin = TRUE;
        break;
      }
    }

    // Regular members may only assign themselves.
    if (!$is_captain_or_admin && $user_id !== (int) $current_user->id()) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
    }

    // Verify the target user is a member of this group.
    /** @var \Drupal\user\Entity\User $target_user */
    $target_user = $this->entityTypeManager()->getStorage('user')->load($user_id);
    if (!$target_user || !$group->getMember($target_user)) {
      return new JsonResponse(['error' => 'User is not a group member.'], 400);
    }

    // Load and validate the paragraph.
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')->load($paragraph_id);
    if (!$paragraph || $paragraph->bundle() !== 'race_day_leg_assignment') {
      return new JsonResponse(['error' => 'Invalid assignment.'], 400);
    }

    // Confirm the paragraph belongs to this group.
    if ($paragraph->getParentEntity()?->id() !== $group->id()) {
      return new JsonResponse(['error' => 'Assignment does not belong to this team.'], 403);
    }

    // If the leg already has a runner, reject the request with a notice.
    if (!$paragraph->get('field_race_day_runner')->isEmpty()) {
      $response = new AjaxResponse();
      $response->addCommand(new MessageCommand('Leg already assigned; you may need to refresh the page.', NULL, ['type' => 'warning']));
      $response->addCommand(new InvokeCommand('html, body', 'animate', [['scrollTop' => 0], 400]));
      return $response;
    }

    $paragraph->set('field_race_day_runner', $target_user);
    $paragraph->save();

    return $this->reRenderTable($group);
  }

  public function remove(Request $request, GroupInterface $group): AjaxResponse|JsonResponse {
    $token = $request->request->get('csrf_token');
    if (!\Drupal::csrfToken()->validate($token, 'remove-runner-' . $group->id())) {
      return new JsonResponse(['error' => 'Invalid token.'], 403);
    }

    $paragraph_id = (int) $request->request->get('paragraph_id');

    $current_user = $this->currentUser();
    $membership = $group->getMember($current_user);

    if (!$membership) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
    }

    $is_captain_or_admin = FALSE;
    foreach ($membership->getRoles() as $role) {
      if (in_array($role->id(), ['race_team-admin', 'race_team-team_captain'])) {
        $is_captain_or_admin = TRUE;
        break;
      }
    }

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')->load($paragraph_id);
    if (!$paragraph || $paragraph->bundle() !== 'race_day_leg_assignment') {
      return new JsonResponse(['error' => 'Invalid assignment.'], 400);
    }

    if ($paragraph->getParentEntity()?->id() !== $group->id()) {
      return new JsonResponse(['error' => 'Assignment does not belong to this team.'], 403);
    }

    // Regular members may only remove themselves.
    if (!$is_captain_or_admin) {
      $runner_id = $paragraph->get('field_race_day_runner')->target_id;
      if ((int) $runner_id !== (int) $current_user->id()) {
        return new JsonResponse(['error' => 'Access denied.'], 403);
      }
    }

    $paragraph->set('field_race_day_runner', NULL);
    $paragraph->save();

    return $this->reRenderTable($group);
  }

  /**
   * Reloads the group and re-renders the runner assignments field.
   */
  private function reRenderTable(GroupInterface $group): AjaxResponse {
    // Reload to bypass the in-memory cache and pick up the saved value.
    // Pass formatter options directly — the field lives inside a Layout Builder
    // layout, so ->view('default') returns empty (no top-level component found).
    $group_fresh = $this->entityTypeManager()->getStorage('group')->loadUnchanged($group->id());
    $build = $group_fresh->get('field_runner_legs')->view([
      'type' => 'entity_reference_revisions_entity_view',
      'label' => 'above',
      'settings' => ['view_mode' => 'default'],
      'weight' => 0,
    ]);

    $html = (string) $this->renderer->renderInIsolation($build);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#runner-assignments-' . $group->id(), $html));
    return $response;
  }

}
