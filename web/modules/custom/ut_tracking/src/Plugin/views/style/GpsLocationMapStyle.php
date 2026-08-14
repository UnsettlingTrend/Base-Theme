<?php

declare(strict_types=1);

namespace Drupal\ut_tracking\Plugin\views\style;

use Drupal\image\Entity\ImageStyle;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Renders one marker per row on a full-page clustered Leaflet map.
 *
 * Reads each row's loaded GpsLocation entity directly ($row->_entity,
 * which Views attaches automatically for any entity-based View) rather
 * than going through configured Views fields/tokens — the same PHP-level
 * data extraction the old MapController used before this View replaced
 * it, just moved into the style plugin's render(). The actual
 * Leaflet/clustering/marker rendering this hands the data to lives in
 * ut_tracking/js/full-map.js, unchanged.
 *
 * The View's own access plugin only gates the page itself (any
 * authenticated user — see views.view.gps_location_map.yml), same as any
 * other row here: the query's LatestPerUserFilter returns every user's
 * latest report regardless of viewer, unfiltered by gps_location entity
 * access (which is admin-only anyway — see
 * GpsLocationAccessControlHandler). Per-viewer filtering therefore has to
 * happen here, in render(): each row is only turned into a marker if
 * _ut_tracking_user_can_view_location() (or "administer ut_tracking")
 * grants the CURRENT viewer access to THAT row's owner — the exact same
 * Location Access group check the profile page's "Current Location"
 * section uses, so a given viewer sees the same set of people's current
 * location everywhere on the site, not a looser set here.
 *
 * @ViewsStyle(
 *   id = "gps_location_map",
 *   title = @Translation("GPS Location Map"),
 *   help = @Translation("Full-page clustered Leaflet map, one marker per row."),
 *   theme = "views_view_unformatted",
 *   display_types = {"normal"}
 * )
 */
class GpsLocationMapStyle extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = FALSE;

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $markers = [];
    $marker_style = ImageStyle::load('map_marker');
    $viewer = \Drupal::currentUser();
    $viewer_is_admin = $viewer->hasPermission('administer ut_tracking');

    foreach ($this->view->result as $row) {
      /** @var \Drupal\ut_tracking\Entity\GpsLocation|null $location */
      $location = $row->_entity ?? NULL;
      if (!$location) {
        continue;
      }

      $user = $location->getOwner();
      if (!$user) {
        continue;
      }

      if (!$viewer_is_admin && !_ut_tracking_user_can_view_location($viewer, $user)) {
        continue;
      }

      $avatar_url = NULL;
      if ($marker_style && !$user->get('user_picture')->isEmpty()) {
        $file = $user->get('user_picture')->entity;
        if ($file) {
          $avatar_url = $marker_style->buildUrl($file->getFileUri());
        }
      }

      $markers[] = [
        'uid' => (int) $user->id(),
        'name' => $user->getDisplayName(),
        'lat' => (float) $location->get('latitude')->value,
        'lng' => (float) $location->get('longitude')->value,
        'recorded' => (int) $location->get('recorded')->value,
        'avatarUrl' => $avatar_url,
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ut-tracking-full-map',
        'class' => ['ut-tracking-full-map'],
      ],
      '#attached' => [
        'library' => ['ut_tracking/full_map'],
        'drupalSettings' => [
          'utTracking' => [
            'markers' => $markers,
          ],
        ],
      ],
      '#cache' => [
        // Location data changes continuously via the API; this page is
        // admin-only and low-traffic, so there's no value in caching a
        // render that's stale the moment the next report comes in.
        'max-age' => 0,
      ],
    ];
  }

}
