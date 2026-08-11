/**
 * @file
 * Renders one marker per user — their most recent GPS location report — on
 * a single full-page Leaflet map, each marker showing that user's avatar.
 * Markers are grouped via Leaflet.markercluster (leaflet_markercluster
 * module, already enabled site-wide) rather than anything hand-rolled.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Minimal HTML-escaper for values interpolated into marker/popup markup.
   * User display names are user-controlled, so this can't be skipped even
   * though core's default username validation already blocks HTML-special
   * characters — defense in depth costs nothing here.
   */
  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function initials(name) {
    var parts = String(name).trim().split(/\s+/);
    var letters = parts.slice(0, 2).map(function (part) {
      return part.charAt(0);
    });
    return escapeHtml(letters.join('').toUpperCase());
  }

  Drupal.behaviors.utTrackingFullMap = {
    attach: function (context) {
      once('ut-tracking-full-map', '#ut-tracking-full-map', context).forEach(function (el) {
        var markers = (drupalSettings.utTracking && drupalSettings.utTracking.markers) || [];

        if (typeof window.L === 'undefined') {
          el.innerHTML = '<div class="ut-tracking-no-data">Map library unavailable.</div>';
          return;
        }

        if (!markers.length) {
          el.innerHTML = '<div class="ut-tracking-no-data">No GPS locations reported yet.</div>';
          return;
        }

        var map = window.L.map(el, {
          attributionControl: true
        });

        window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
          subdomains: 'abcd'
        }).addTo(map);

        var clusterGroup = window.L.markerClusterGroup();
        var latLngs = [];

        markers.forEach(function (marker) {
          var name = escapeHtml(marker.name);
          var html = marker.avatarUrl
            ? '<div class="ut-tracking-marker"><img src="' + escapeHtml(marker.avatarUrl) + '" alt=""></div>'
            : '<div class="ut-tracking-marker ut-tracking-marker--fallback">' + initials(marker.name) + '</div>';

          var icon = window.L.divIcon({
            html: html,
            className: 'ut-tracking-marker-wrapper',
            iconSize: [40, 40]
          });

          var when = marker.recorded ? new Date(marker.recorded * 1000).toLocaleString() : 'unknown time';
          var latLng = [marker.lat, marker.lng];

          var leafletMarker = window.L.marker(latLng, { icon: icon })
            .bindPopup('<strong>' + name + '</strong><br>' + escapeHtml(when));

          clusterGroup.addLayer(leafletMarker);
          latLngs.push(latLng);
        });

        map.addLayer(clusterGroup);

        if (latLngs.length === 1) {
          map.setView(latLngs[0], 14);
        }
        else {
          map.fitBounds(window.L.latLngBounds(latLngs), { padding: [40, 40] });
        }
      });
    }
  };
})(Drupal, drupalSettings, once);
