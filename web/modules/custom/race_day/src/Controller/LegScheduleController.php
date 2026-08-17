<?php

declare(strict_types=1);

namespace Drupal\race_day\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles the leg schedule modal opened from the Runner Assignments table.
 */
class LegScheduleController extends ControllerBase {

  public function __construct(protected RendererInterface $renderer) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('renderer'));
  }

  /**
   * Builds the modal's content. Viewable by anyone who can view the group
   * page (see race_day.leg_schedule_modal's access check); edit controls
   * only render for a Race Day Coordinator or someone separately granted
   * "modify any actual times".
   */
  public function modal(GroupInterface $group, ParagraphInterface $paragraph): array {
    $this->validateAssignment($group, $paragraph);
    return _race_day_build_leg_schedule_modal($group, $paragraph, $this->canEditActualTimes());
  }

  /**
   * Saves an edited Start Time, End Time, or Time value — or, if 'reset' is
   * set, clears the corresponding actual field instead.
   */
  public function update(Request $request, GroupInterface $group, ParagraphInterface $paragraph): AjaxResponse|JsonResponse {
    if (!$this->canEditActualTimes()) {
      return new JsonResponse(['error' => 'Access denied.'], 403);
    }

    $this->validateAssignment($group, $paragraph);

    $field = $request->request->get('field');
    $is_reset = $request->request->get('reset') === '1';

    if ($is_reset) {
      switch ($field) {
        case 'start':
          _race_day_reset_actual_start($paragraph);
          break;

        case 'finish':
          _race_day_reset_actual_finish($paragraph);
          break;

        case 'time':
          _race_day_reset_actual_time($paragraph);
          break;

        default:
          return new JsonResponse(['error' => 'Invalid field.'], 400);
      }
    }
    else {
      $value = (string) $request->request->get('value');

      switch ($field) {
        case 'start':
          $ms = _race_day_parse_time_of_day_to_ms($value);
          if ($ms === NULL) {
            return new JsonResponse(['error' => 'Enter a valid time.'], 400);
          }
          if (!_race_day_apply_actual_start($paragraph, $ms)) {
            return new JsonResponse(['error' => 'No date to anchor this time to yet — set an expected schedule first.'], 400);
          }
          break;

        case 'finish':
          $ms = _race_day_parse_time_of_day_to_ms($value);
          if ($ms === NULL) {
            return new JsonResponse(['error' => 'Enter a valid time.'], 400);
          }
          if (!_race_day_apply_actual_finish($paragraph, $ms)) {
            return new JsonResponse(['error' => 'No date to anchor this time to yet — set an expected schedule first.'], 400);
          }
          break;

        case 'time':
          $ms = _race_day_parse_duration_to_ms($value);
          if ($ms === NULL) {
            return new JsonResponse(['error' => 'Enter a valid duration, e.g. 1:15:30.'], 400);
          }
          _race_day_apply_actual_time($paragraph, $ms);
          break;

        default:
          return new JsonResponse(['error' => 'Invalid field.'], 400);
      }
    }

    // The edit above can change which leg is actually "out" right now —
    // re-check the same way a pace change does, only once every affected
    // field/moment has settled.
    _race_day_maybe_update_running_status($group);

    $response = new AjaxResponse();

    $modal_build = _race_day_build_leg_schedule_modal($group, $paragraph, TRUE);
    $modal_html = (string) $this->renderer->renderInIsolation($modal_build);
    $response->addCommand(new ReplaceCommand('#leg-schedule-modal-' . $paragraph->id(), $modal_html));

    // Reload to bypass the in-memory cache and pick up the saved values —
    // same approach as AssignRunnerController::reRenderTable().
    $group_fresh = $this->entityTypeManager()->getStorage('group')->loadUnchanged($group->id());
    $table_build = $group_fresh->get('field_runner_legs')->view([
      'type' => 'entity_reference_revisions_entity_view',
      'label' => 'above',
      'settings' => ['view_mode' => 'default'],
      'weight' => 0,
    ]);
    $table_html = (string) $this->renderer->renderInIsolation($table_build);
    $response->addCommand(new ReplaceCommand('#runner-assignments-' . $group->id(), $table_html));

    return $response;
  }

  /**
   * Confirms the paragraph is a leg assignment belonging to this group, with
   * a runner assigned. The Runner Assignments table only links to this route
   * when a leg has a runner (see _race_day_preprocess_field_runner_legs()) —
   * this re-checks it server-side too, since there's nothing meaningful to
   * schedule for an unassigned leg.
   */
  private function validateAssignment(GroupInterface $group, ParagraphInterface $paragraph): void {
    if ($paragraph->bundle() !== 'race_day_leg_assignment' || $paragraph->getParentEntity()?->id() !== $group->id()) {
      throw new NotFoundHttpException();
    }
    if (!$paragraph->hasField('field_race_day_runner') || $paragraph->get('field_race_day_runner')->isEmpty()) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Whether the current user can edit actual times on any leg assignment —
   * full Race Day Coordinators, or anyone separately granted the narrower
   * "modify any actual times" permission.
   */
  private function canEditActualTimes(): bool {
    return $this->currentUser()->hasPermission('manage race day groups')
      || $this->currentUser()->hasPermission('modify any actual times');
  }

}
