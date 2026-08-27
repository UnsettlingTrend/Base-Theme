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
    // A Race Day Coordinator manages assignments on every team, whether or
    // not they're a member of this particular one.
    $is_captain_or_admin = _race_day_can_manage_team_assignments($group, $current_user);

    if (!$membership && !$is_captain_or_admin) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
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

    // Only field_race_day_runner changes here — field_actual_start,
    // field_actual_finish, and field_actual_time are deliberately left
    // untouched. A leg can already have real Actual data recorded (entered
    // via the schedule modal — LegScheduleController::update()) before it
    // ever has a runner, or carried over from whoever held the slot before;
    // either way, assigning someone to it must never clear that.
    $paragraph->set('field_race_day_runner', $target_user);
    $paragraph->save();

    // A new runner changes who's actually "out" on the course right now —
    // same re-check a time edit or pace change triggers
    // (LegScheduleController::update(), race_day_group_relationship_update()).
    _race_day_maybe_update_running_status($group);

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
    // A Race Day Coordinator manages assignments on every team, whether or
    // not they're a member of this particular one.
    $is_captain_or_admin = _race_day_can_manage_team_assignments($group, $current_user);

    if (!$membership && !$is_captain_or_admin) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
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

    // Only field_race_day_runner is cleared — field_actual_start,
    // field_actual_finish, and field_actual_time are deliberately left as
    // recorded. Removing a runner from a leg (e.g. a scheduling mistake,
    // or freeing the slot for someone else) must never lose real race-day
    // times just because the slot is temporarily — or permanently —
    // unassigned.
    $paragraph->set('field_race_day_runner', NULL);
    $paragraph->save();

    // Removing a runner can leave nobody "out" on this leg, or — if this
    // was the currently-running leg — reopen the question of which leg is
    // actually current now. Same re-check assign() above and a time/pace
    // edit both trigger.
    _race_day_maybe_update_running_status($group);

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
