<?php

declare(strict_types=1);

namespace Drupal\race_day\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\group\Entity\GroupInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\UserInterface;
use Drupal\ut_tracking\Entity\GpsLocation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Renders a race team's full-page live GPS map.
 *
 * The map itself (Leaflet, marker clustering, the My Team/All Teams toggle
 * control) lives entirely in race_day/team_live_map (js/team-live-map.js) —
 * this controller's only job is gathering the two marker datasets server
 * side and handing them over via drupalSettings, same division of labor as
 * ut_tracking's GpsLocationMapStyle/full-map.js.
 */
class TeamLiveMapController extends ControllerBase {

  /**
   * Title callback.
   */
  public function title(GroupInterface $group): TranslatableMarkup {
    return $this->t('@team — Live Map', ['@team' => $group->label()]);
  }

  /**
   * Page callback.
   */
  public function page(GroupInterface $group): array {
    if ($group->bundle() !== 'race_team') {
      throw new NotFoundHttpException();
    }

    $viewer = $this->currentUser();
    $marker_style = ImageStyle::load('map_marker');

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'race-day-live-map',
        'class' => ['race-day-live-map'],
      ],
      '#attached' => [
        'library' => ['race_day/team_live_map'],
        'drupalSettings' => [
          'raceDayLiveMap' => [
            'myTeam' => $this->buildMyTeamMarkers($group, $viewer, $marker_style),
            'allTeams' => $this->buildAllTeamsMarkers($group, $viewer, $marker_style),
            'legs' => $this->buildLegPolylines($group),
          ],
        ],
      ],
      '#cache' => [
        // Live GPS data changes continuously via the tracking API; there's
        // no value in caching a render that's stale the moment the next
        // location report comes in — same reasoning as
        // GpsLocationMapStyle::render().
        'max-age' => 0,
      ],
    ];
  }

  /**
   * One marker per member of $group the viewer is authorized to see.
   *
   * Only the marker for the team's current runner (if any) gets an
   * expectedLegFinish — everyone else keeps the plain "recorded" timestamp
   * (team-live-map.js falls back to that whenever expectedLegFinish is
   * absent), since a leg finish estimate isn't meaningful for a member who
   * isn't presently out on a leg. The current runner specifically also
   * falls back to an estimated position (pace/elapsed-time projection along
   * their leg's route — see _race_day_estimate_runner_position()) if they
   * haven't reported a GPS location yet; nobody else gets that fallback,
   * since it's only ever computed against the leg someone is presently
   * running.
   *
   * @return array<int, array{uid: int, name: string, lat: float, lng: float, recorded: int|null, estimated: bool, avatarUrl: string|null, expectedLegFinish: string|null}>
   */
  private function buildMyTeamMarkers(GroupInterface $group, AccountInterface $viewer, ?ImageStyle $marker_style): array {
    $markers = [];
    $is_admin = $viewer->hasPermission('administer ut_tracking');

    $current_assignment = _race_day_current_running_assignment_for_team($group);
    $current_runner_uid = ($current_assignment && !$current_assignment->get('field_race_day_runner')->isEmpty())
      ? (int) $current_assignment->get('field_race_day_runner')->target_id
      : NULL;

    foreach ($this->teamMemberUsers($group) as $member) {
      // Same authorization _ut_tracking_get_authorized_current_location()
      // already applies (direct location_access grant, or membership in a
      // team listed in the member's own field_authorized_race_teams) — a
      // teammate who hasn't authorized this team still doesn't show up
      // here just because they're a fellow member. Checked directly
      // (rather than just treating that helper's NULL as "skip") since an
      // estimated position must never fill in for someone who isn't
      // authorized in the first place — only for a lack of GPS data from
      // someone who is.
      if (!$is_admin && !_ut_tracking_user_can_view_location($viewer, $member)) {
        continue;
      }

      $is_current_runner = $current_runner_uid !== NULL && $current_runner_uid === (int) $member->id();

      $position = $this->currentOrEstimatedPosition(
        _ut_tracking_get_authorized_current_location($viewer, $member),
        $is_current_runner ? $group : NULL,
        $is_current_runner ? $current_assignment : NULL,
      );
      if (!$position) {
        continue;
      }

      $markers[] = [
        'uid' => (int) $member->id(),
        'name' => $member->getDisplayName(),
        'lat' => $position['lat'],
        'lng' => $position['lng'],
        'recorded' => $position['recorded'],
        'estimated' => $position['estimated'],
        'avatarUrl' => $this->userAvatarUrl($member, $marker_style),
        'expectedLegFinish' => $is_current_runner ? _race_day_expected_finish_display($current_assignment) : NULL,
      ];
    }

    return $markers;
  }

  /**
   * One marker per race_team in $group's race, at its current runner's
   * location — labeled with the team's own name/icon, not the runner's.
   *
   * Every marker here is by definition the current runner for its team
   * (raceTeams() only includes teams with one), so every marker here is
   * eligible for the estimated-position fallback — see
   * buildMyTeamMarkers()'s docblock re: what that means and when it kicks
   * in.
   *
   * @return array<int, array{teamId: int, teamName: string, runnerName: string, lat: float, lng: float, recorded: int|null, estimated: bool, iconUrl: string|null, expectedLegFinish: string|null, expectedTotalFinish: string|null}>
   */
  private function buildAllTeamsMarkers(GroupInterface $group, AccountInterface $viewer, ?ImageStyle $marker_style): array {
    $markers = [];
    $is_admin = $viewer->hasPermission('administer ut_tracking');

    foreach ($this->raceTeams($group) as $team) {
      $assignment = _race_day_current_running_assignment_for_team($team);
      if (!$assignment || $assignment->get('field_race_day_runner')->isEmpty()) {
        continue;
      }
      $runner = $assignment->get('field_race_day_runner')->entity;
      if (!$runner instanceof UserInterface) {
        continue;
      }

      if (!$is_admin && !_ut_tracking_user_can_view_location($viewer, $runner)) {
        continue;
      }

      $position = $this->currentOrEstimatedPosition(
        _ut_tracking_get_authorized_current_location($viewer, $runner),
        $team,
        $assignment,
      );
      if (!$position) {
        continue;
      }

      $markers[] = [
        'teamId' => (int) $team->id(),
        'teamName' => $team->label(),
        // Not shown as the marker's own label (that's teamName/iconUrl —
        // the whole point of this view is the team, not the runner) but
        // needed for the popup's "Navigate to <first name>" link, which is
        // about the runner actually at this position.
        'runnerName' => $runner->getDisplayName(),
        'lat' => $position['lat'],
        'lng' => $position['lng'],
        'recorded' => $position['recorded'],
        'estimated' => $position['estimated'],
        'iconUrl' => $this->teamIconUrl($team, $marker_style),
        'expectedLegFinish' => _race_day_expected_finish_display($assignment),
        'expectedTotalFinish' => _race_day_expected_total_finish_display($team),
      ];
    }

    return $markers;
  }

  /**
   * Resolves a marker's position: $location if reported, else — only when
   * $estimate_group/$estimate_assignment are both given, i.e. only for a
   * current runner — a pace/elapsed-time estimate along their leg's route
   * (_race_day_estimate_runner_position()). NULL if neither is available.
   *
   * @return array{lat: float, lng: float, recorded: int|null, estimated: bool}|null
   */
  private function currentOrEstimatedPosition(
    ?GpsLocation $location,
    ?GroupInterface $estimate_group,
    ?ParagraphInterface $estimate_assignment,
  ): ?array {
    if ($location) {
      return [
        'lat' => (float) $location->get('latitude')->value,
        'lng' => (float) $location->get('longitude')->value,
        'recorded' => (int) $location->get('recorded')->value,
        'estimated' => FALSE,
      ];
    }

    if (!$estimate_group || !$estimate_assignment) {
      return NULL;
    }

    $estimate = _race_day_estimate_runner_position($estimate_group, $estimate_assignment);
    if (!$estimate) {
      return NULL;
    }

    return [
      'lat' => $estimate['lat'],
      'lng' => $estimate['lng'],
      'recorded' => NULL,
      'estimated' => TRUE,
    ];
  }

  /**
   * Every leg of $group's race that has route data, in leg order.
   *
   * @return array<int, array{label: string, legNumber: int, distance: string, polyline: string}>
   */
  private function buildLegPolylines(GroupInterface $group): array {
    $legs = [];
    foreach ($this->raceLegs($group) as $leg) {
      $polyline = $leg->getPolyline();
      if ($polyline === NULL || $polyline === '') {
        continue;
      }

      $legs[] = [
        'label' => $leg->label(),
        'legNumber' => (int) ($leg->get('leg_number')->value ?? 0),
        'distance' => (string) ($leg->getDistance() ?? ''),
        'polyline' => $polyline,
      ];
    }

    return $legs;
  }

  /**
   * Every leg of $group's race, in leg order.
   *
   * @return array<int, \Drupal\race_day\Entity\RaceLeg>
   */
  private function raceLegs(GroupInterface $group): array {
    if (!$group->hasField('field_race') || $group->get('field_race')->isEmpty()) {
      return [];
    }
    $race = $group->get('field_race')->entity;
    if (!$race) {
      return [];
    }

    $leg_storage = $this->entityTypeManager()->getStorage('race_leg');
    $ids = $leg_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('race_id', $race->id())
      ->sort('leg_number', 'ASC')
      ->execute();

    return $ids ? $leg_storage->loadMultiple($ids) : [];
  }

  /**
   * Loads every user who is a member of $group, keyed by uid.
   *
   * Queries group_relationship directly by gid/plugin_id rather than
   * through Group module's relationship-chain API — the same approach (and
   * the same reason: avoiding the virtual group_relationship_id join) used
   * throughout this module, e.g. _race_day_get_member_pace().
   *
   * @return array<int, \Drupal\user\UserInterface>
   */
  private function teamMemberUsers(GroupInterface $group): array {
    $relationships = $this->entityTypeManager()->getStorage('group_relationship')->loadByProperties([
      'gid' => $group->id(),
      'plugin_id' => 'group_membership',
    ]);

    $users = [];
    $user_storage = $this->entityTypeManager()->getStorage('user');
    foreach ($relationships as $relationship) {
      $uid = (int) $relationship->get('entity_id')->target_id;
      if (isset($users[$uid])) {
        continue;
      }
      $user = $user_storage->load($uid);
      if ($user instanceof UserInterface) {
        $users[$uid] = $user;
      }
    }

    return $users;
  }

  /**
   * Every race_team group in the same race as $group, including itself.
   *
   * Falls back to just $group if it has no race set yet (shouldn't happen —
   * field_race is required — but avoids an empty-condition query if it
   * ever does).
   *
   * @return array<int, \Drupal\group\Entity\GroupInterface>
   */
  private function raceTeams(GroupInterface $group): array {
    if (!$group->hasField('field_race') || $group->get('field_race')->isEmpty()) {
      return [(int) $group->id() => $group];
    }

    $storage = $this->entityTypeManager()->getStorage('group');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'race_team')
      ->condition('field_race', $group->get('field_race')->target_id)
      ->execute();

    return $ids ? $storage->loadMultiple($ids) : [(int) $group->id() => $group];
  }

  /**
   * Builds a map_marker-styled URL for a user's avatar, or NULL.
   */
  private function userAvatarUrl(UserInterface $user, ?ImageStyle $marker_style): ?string {
    if (!$marker_style || $user->get('user_picture')->isEmpty()) {
      return NULL;
    }
    $file = $user->get('user_picture')->entity;
    return $file ? $marker_style->buildUrl($file->getFileUri()) : NULL;
  }

  /**
   * Builds a map_marker-styled URL for a race_team's icon, or NULL.
   */
  private function teamIconUrl(GroupInterface $team, ?ImageStyle $marker_style): ?string {
    if (!$marker_style || !$team->hasField('field_icon') || $team->get('field_icon')->isEmpty()) {
      return NULL;
    }
    $media = $team->get('field_icon')->entity;
    if (!$media || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }
    $file = $media->get('field_media_image')->entity;
    return $file ? $marker_style->buildUrl($file->getFileUri()) : NULL;
  }

}
