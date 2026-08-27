/**
 * @file
 * Renders a race team's live GPS map: a Leaflet map with a custom control
 * (styled and positioned like the zoom +/- control) toggling between two
 * mutually-exclusive marker layers —
 *
 * - "My Team": one marker per team member, showing that member's own
 *   avatar/name at their current location (same visual language as
 *   ut_tracking/full_map's personal markers).
 * - "All Teams": one marker per team in the race, showing that TEAM's own
 *   name/icon — not the runner's — at its current runner's location.
 *
 * Both datasets are computed server-side (TeamLiveMapController) and handed
 * over via drupalSettings.raceDayLiveMap; this file only draws them.
 */
(function (Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Minimal HTML-escaper for values interpolated into marker/popup markup.
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

  function firstName(name) {
    var trimmed = String(name).trim();
    return trimmed.split(/\s+/)[0] || trimmed;
  }

  /**
   * The popup's closing "Navigate to <first name>" link — a Google Maps
   * directions deep link to the marker's own lat/lng, same URL pattern
   * ut_tracking's "Navigate There" button and strava_api's leg start/end
   * buttons use. $name is the runner's own name even on a team marker
   * (marker.runnerName) — the link is about who's actually at this
   * position, not the team label the marker itself is drawn with.
   */
  function navigateLink(name, marker) {
    var url = 'https://www.google.com/maps/dir/?api=1&destination='
      + encodeURIComponent(marker.lat + ',' + marker.lng);
    return '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">'
      + 'Navigate to ' + escapeHtml(firstName(name)) + '</a>';
  }

  var LEG_COLORS = [
    '#fc4c02', // Strava orange
    '#1a73e8', // blue
    '#0f9d58', // green
    '#f4b400', // yellow
    '#ab47bc', // purple
    '#00acc1', // cyan
    '#ff7043', // deep orange
    '#43a047', // dark green
    '#e53935', // red
    '#5c6bc0', // indigo
  ];

  /**
   * Decodes a Google-encoded polyline string into an array of [lat, lng]
   * pairs. Same algorithm/implementation as race-day-leg-map.js's
   * decodePolyline() — duplicated rather than shared, since it's the only
   * piece either file needs from the other and isn't worth a shared module.
   */
  function decodePolyline(encoded) {
    var points = [];
    var index = 0;
    var lat = 0;
    var lng = 0;

    while (index < encoded.length) {
      var b, shift = 0, result = 0;
      do {
        b = encoded.charCodeAt(index++) - 63;
        result |= (b & 0x1f) << shift;
        shift += 5;
      } while (b >= 0x20);
      lat += (result & 1) ? ~(result >> 1) : (result >> 1);

      shift = 0;
      result = 0;
      do {
        b = encoded.charCodeAt(index++) - 63;
        result |= (b & 0x1f) << shift;
        shift += 5;
      } while (b >= 0x20);
      lng += (result & 1) ? ~(result >> 1) : (result >> 1);

      points.push([lat / 1e5, lng / 1e5]);
    }

    return points;
  }

  /**
   * Draws every leg's route as a permanent (non-toggleable) polyline layer,
   * so the course itself is always visible regardless of which marker
   * layer is active.
   *
   * @return {Array} The bounds ([[lat,lng], ...] flattened) of every drawn
   *   leg, for use as a fitBounds() fallback when there are no markers yet.
   */
  function drawLegPolylines(map, legs) {
    var allPoints = [];

    legs.forEach(function (leg, i) {
      var points;
      try {
        points = decodePolyline(leg.polyline);
      }
      catch (e) {
        return;
      }
      if (!points.length) {
        return;
      }

      var color = LEG_COLORS[i % LEG_COLORS.length];
      var line = window.L.polyline(points, {
        color: color,
        weight: 4,
        opacity: 0.9
      }).addTo(map);

      var label = 'Leg ' + leg.legNumber + ': ' + leg.label;
      if (leg.distance) {
        label += ' (' + parseFloat(leg.distance).toFixed(2) + ' mi)';
      }
      line.bindTooltip(label, { sticky: true });

      allPoints = allPoints.concat(points);
    });

    return allPoints;
  }

  /**
   * A marker's popup line for a GPS report's own recorded time — the
   * fallback when there's no more specific schedule info to show, same as
   * before that schedule info existed. NULL for an estimated-position
   * marker (marker.recorded is never set for one — see
   * TeamLiveMapController::currentOrEstimatedPosition()) — a "position
   * estimated at" note is what those show instead (estimatedNoteLine()).
   */
  function recordedTimeLine(marker) {
    if (marker.estimated) {
      return null;
    }
    var when = marker.recorded ? new Date(marker.recorded * 1000).toLocaleString() : 'unknown time';
    return escapeHtml(when);
  }

  /**
   * The note appended to an estimated-position marker's popup — pace/
   * elapsed-time projection along the route, not a real GPS report, so it
   * has to read as clearly different from one.
   */
  function estimatedNoteLine(marker) {
    return marker.estimated ? '<em>Position and time estimated from pace</em>' : null;
  }

  /**
   * Popup body for a My Team (person) marker: that member's expected leg
   * finish if they're the team's current runner (the only case
   * expectedLegFinish is ever set — see TeamLiveMapController), else the
   * GPS report's recorded time as before — plus an estimated-position note
   * when applicable.
   */
  function personPopupBody(marker) {
    var lines = [];
    if (marker.expectedLegFinish) {
      lines.push('Expected Leg Finish: ' + escapeHtml(marker.expectedLegFinish));
    }
    else {
      var recorded = recordedTimeLine(marker);
      if (recorded) {
        lines.push(recorded);
      }
    }
    var estimatedNote = estimatedNoteLine(marker);
    if (estimatedNote) {
      lines.push(estimatedNote);
    }
    lines.push(navigateLink(marker.name, marker));
    return lines.join('<br>');
  }

  /**
   * Popup body for an All Teams (team) marker: the current runner's
   * expected leg finish and the team's overall expected finish (its last
   * leg's expected finish), whichever of the two are available — plus an
   * estimated-position note when applicable.
   */
  function teamPopupBody(marker) {
    var lines = [];
    if (marker.expectedLegFinish) {
      lines.push('Expected Leg Finish: ' + escapeHtml(marker.expectedLegFinish));
    }
    if (marker.expectedTotalFinish) {
      lines.push('Expected Total Finish: ' + escapeHtml(marker.expectedTotalFinish));
    }
    if (!lines.length) {
      var recorded = recordedTimeLine(marker);
      if (recorded) {
        lines.push(recorded);
      }
    }
    var estimatedNote = estimatedNoteLine(marker);
    if (estimatedNote) {
      lines.push(estimatedNote);
    }
    lines.push(navigateLink(marker.runnerName, marker));
    return lines.join('<br>');
  }

  /**
   * Builds a marker-cluster layer plus the list of lat/lngs it covers.
   *
   * @param {Array} markers
   *   Either myTeam or allTeams entries from drupalSettings.
   * @param {string} modifierClass
   *   'race-day-marker--person' or 'race-day-marker--team'.
   * @param {function} labelOf
   *   Returns a marker's display label (person name or team name).
   * @param {function} imageUrlOf
   *   Returns a marker's image URL (avatarUrl or iconUrl), or falsy.
   * @param {function} popupBodyOf
   *   Returns a marker's popup body HTML (below the bolded label) —
   *   personPopupBody or teamPopupBody.
   *
   * @return {{layer: L.MarkerClusterGroup, latLngs: Array}}
   */
  function buildLayer(markers, modifierClass, labelOf, imageUrlOf, popupBodyOf) {
    var group = window.L.markerClusterGroup();
    var latLngs = [];

    markers.forEach(function (marker) {
      var label = escapeHtml(labelOf(marker));
      var imageUrl = imageUrlOf(marker);
      // Estimated markers (pace/elapsed-time projection, not a reported
      // GPS fix — see TeamLiveMapController) get a dashed outline so
      // they're visually distinct from a live position at a glance, not
      // just on click.
      var classes = 'race-day-marker ' + modifierClass + (marker.estimated ? ' race-day-marker--estimated' : '');
      var html = imageUrl
        ? '<div class="' + classes + '"><img src="' + escapeHtml(imageUrl) + '" alt=""></div>'
        : '<div class="' + classes + ' race-day-marker--fallback">' + initials(labelOf(marker)) + '</div>';

      var icon = window.L.divIcon({
        html: html,
        className: 'race-day-marker-wrapper',
        iconSize: [40, 40]
      });

      var latLng = [marker.lat, marker.lng];

      var leafletMarker = window.L.marker(latLng, { icon: icon })
        .bindPopup('<strong>' + label + '</strong><br>' + popupBodyOf(marker));

      group.addLayer(leafletMarker);
      latLngs.push(latLng);
    });

    return { layer: group, latLngs: latLngs };
  }

  /**
   * A Leaflet control offering two mutually-exclusive buttons, styled like
   * the standard zoom control (leaflet-bar) and placed just beneath it.
   */
  var TeamToggleControl = (typeof window.L !== 'undefined' && window.L.Control) ? window.L.Control.extend({
    options: { position: 'topright' },

    initialize: function (options) {
      window.L.Util.setOptions(this, options);
      this._active = options.active;
    },

    onAdd: function () {
      var container = window.L.DomUtil.create('div', 'leaflet-bar race-day-team-toggle');
      var buttons = {
        myTeam: this._makeButton(container, Drupal.t('My Team')),
        allTeams: this._makeButton(container, Drupal.t('All Teams'))
      };

      var setActive = function (key) {
        Object.keys(buttons).forEach(function (buttonKey) {
          window.L.DomUtil.removeClass(buttons[buttonKey], 'is-active');
        });
        window.L.DomUtil.addClass(buttons[key], 'is-active');
      };
      setActive(this._active);

      var onChange = this.options.onChange;
      Object.keys(buttons).forEach(function (key) {
        window.L.DomEvent.on(buttons[key], 'click', window.L.DomEvent.stop);
        window.L.DomEvent.on(buttons[key], 'click', function () {
          setActive(key);
          onChange(key);
        });
      });

      window.L.DomEvent.disableClickPropagation(container);
      return container;
    },

    _makeButton: function (container, label) {
      var button = window.L.DomUtil.create('a', 'race-day-team-toggle__button', container);
      button.href = '#';
      button.textContent = label;
      return button;
    }
  }) : null;

  Drupal.behaviors.raceDayLiveMap = {
    attach: function (context) {
      once('race-day-live-map', '#race-day-live-map', context).forEach(function (el) {
        var settings = drupalSettings.raceDayLiveMap || { myTeam: [], allTeams: [], legs: [] };
        var myTeamMarkers = settings.myTeam || [];
        var allTeamsMarkers = settings.allTeams || [];
        var legs = settings.legs || [];

        if (typeof window.L === 'undefined') {
          el.innerHTML = '<div class="race-day-live-map-no-data">Map library unavailable.</div>';
          return;
        }

        if (!myTeamMarkers.length && !allTeamsMarkers.length && !legs.length) {
          el.innerHTML = '<div class="race-day-live-map-no-data">No GPS locations reported yet.</div>';
          return;
        }

        var layers = {
          myTeam: buildLayer(
            myTeamMarkers,
            'race-day-marker--person',
            function (marker) { return marker.name; },
            function (marker) { return marker.avatarUrl; },
            personPopupBody
          ),
          allTeams: buildLayer(
            allTeamsMarkers,
            'race-day-marker--team',
            function (marker) { return marker.teamName; },
            function (marker) { return marker.iconUrl; },
            teamPopupBody
          )
        };

        // Default to My Team unless it's empty and All Teams has data.
        var current = (!myTeamMarkers.length && allTeamsMarkers.length) ? 'allTeams' : 'myTeam';

        var map = window.L.map(el, { attributionControl: true });

        window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
          subdomains: 'abcd'
        }).addTo(map);

        // Stacks directly beneath the zoom control (same topleft corner,
        // added second) — the standard expand/collapse icon toggle and
        // "View Fullscreen"/"Exit Fullscreen" title are all handled by the
        // plugin itself (leaflet/leaflet.fullscreen).
        if (window.L.control && window.L.control.fullscreen) {
          window.L.control.fullscreen({ position: 'topleft' }).addTo(map);
        }

        layers[current].layer.addTo(map);

        // The route itself is permanent context, not part of the toggle —
        // drawn once, underneath whichever marker layer is active.
        var legPoints = drawLegPolylines(map, legs);

        function fitToActive() {
          // Prefer the active layer's own markers; fall back to the race
          // route so there's still something sensible to frame before any
          // GPS data comes in.
          var latLngs = layers[current].latLngs.length ? layers[current].latLngs : legPoints;
          if (latLngs.length === 1) {
            map.setView(latLngs[0], 14);
          }
          else if (latLngs.length > 1) {
            map.fitBounds(window.L.latLngBounds(latLngs), { padding: [40, 40] });
          }
          else {
            map.setView([0, 0], 2);
          }
        }

        fitToActive();

        if (TeamToggleControl) {
          new TeamToggleControl({
            active: current,
            onChange: function (key) {
              if (key === current) {
                return;
              }
              map.removeLayer(layers[current].layer);
              current = key;
              layers[current].layer.addTo(map);
              fitToActive();
            }
          }).addTo(map);
        }
      });
    }
  };
})(Drupal, drupalSettings, once);
