<?php

namespace Drupal\race_day\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles AJAX updates to the race team request status field.
 */
class RequestStatusController extends ControllerBase {

  public function __construct(protected RendererInterface $renderer) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('renderer'));
  }

  /**
   * Access callback for the update route.
   *
   * Grants access if the user has any review permission. For
   * 'review own race team requests', also verifies the submission belongs to
   * a team the user is a member of.
   */
  public function access(AccountInterface $account, Request $request): AccessResult {
    if ($account->hasPermission('review all race team requests')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    if ($account->hasPermission('review own race team requests')) {
      $sid = (int) $request->request->get('sid');
      if ($sid) {
        $submission = $this->entityTypeManager()
          ->getStorage('webform_submission')
          ->load($sid);
        if ($submission) {
          $team_id = (int) $submission->getElementData('race_team');
          if ($team_id) {
            $group = $this->entityTypeManager()->getStorage('group')->load($team_id);
            if ($group && $group->getMember($account)) {
              return AccessResult::allowed()->cachePerUser()->addCacheableDependency($submission);
            }
          }
        }
      }
      return AccessResult::forbidden()->cachePerUser();
    }

    return AccessResult::forbidden()->cachePerPermissions();
  }

  public function update(Request $request): AjaxResponse|JsonResponse {
    $sid = (int) $request->request->get('sid');
    $status = $request->request->get('status');

    if (!$sid || $status === NULL) {
      return new JsonResponse(['error' => 'Missing parameters.'], 400);
    }

    $webform_id = 'race_day_runner_team_request';
    $webform = $this->entityTypeManager()->getStorage('webform')->load($webform_id);
    if (!$webform) {
      return new JsonResponse(['error' => 'Webform not found.'], 404);
    }

    // Validate the status value against the webform element options.
    $element = $webform->getElement('request_status');
    $allowed = array_keys($element['#options'] ?? []);
    if (!in_array($status, $allowed, TRUE)) {
      return new JsonResponse(['error' => 'Invalid status value.'], 400);
    }

    $submission = $this->entityTypeManager()
      ->getStorage('webform_submission')
      ->load($sid);

    if (!$submission || $submission->getWebform()->id() !== $webform_id) {
      return new JsonResponse(['error' => 'Submission not found.'], 404);
    }

    $submission->setElementData('request_status', $status);
    // Saving triggers race_day_webform_submission_update(), which syncs group
    // membership and clears leg assignments synchronously before this
    // returns — so the Runner Assignments and Team Members blocks re-rendered
    // below already reflect the new status.
    $submission->save();

    $response = new AjaxResponse();

    $team_id = (int) $submission->getElementData('race_team');
    $group = $team_id ? $this->entityTypeManager()->getStorage('group')->load($team_id) : NULL;
    if (!$group) {
      // No team to re-render against — return an empty command list so the
      // JS's ajax.success() still has an array to iterate.
      return $response;
    }

    // Re-render the Runner Assignments field — approving/denying a request
    // can add, remove, or clear leg slot assignments.
    $group_fresh = $this->entityTypeManager()->getStorage('group')->loadUnchanged($group->id());
    $legs_build = $group_fresh->get('field_runner_legs')->view([
      'type' => 'entity_reference_revisions_entity_view',
      'label' => 'above',
      'settings' => ['view_mode' => 'default'],
      'weight' => 0,
    ]);
    $legs_html = (string) $this->renderer->renderInIsolation($legs_build);
    $response->addCommand(new ReplaceCommand('#runner-assignments-' . $group->id(), $legs_html));

    // Re-render the Team Members view — approving/denying a request adds or
    // removes the requester from the group's membership list. The view's
    // "tag" cache plugin caches query results independently of render
    // caching, and Group's membership-list cache tags aren't reliably
    // invalidated by a webform submission save, so force this specific
    // execution to skip that cache plugin rather than risk serving a stale
    // member list.
    //
    // Deliberately NOT using $view->live_preview to do this: that flag also
    // makes views_ui inject its "quick edit" preview overlay (Title/Content/
    // Filter criteria/etc, see ViewsUiThemeHooks::preprocessViewsView()) into
    // every section of the output for any user with views-editing access —
    // which has no place in an AJAX-swapped fragment. Swapping the display's
    // cache plugin directly gets the same "always fresh" result without
    // tripping that.
    $members_view = Views::getView('race_team_members');
    if ($members_view) {
      $members_view->setDisplay('block_1');
      $members_view->display_handler->setOption('cache', ['type' => 'none', 'options' => []]);
      $members_view->setShowAdminLinks(FALSE);
      $members_build = $members_view->buildRenderable('block_1', [$group->id()], FALSE);
      $members_html = (string) $this->renderer->renderInIsolation($members_build);
      $response->addCommand(new ReplaceCommand('#race-team-members-' . $group->id(), $members_html));
    }

    return $response;
  }

}
