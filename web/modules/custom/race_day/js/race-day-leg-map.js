/**
 * @file
 * Renders all race leg polylines on a single shared Leaflet map and supports
 * hover-highlighting from the legs table on the same page.
 */
(function (Drupal, once) {
  'use strict';

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

  var NORMAL_WEIGHT  = 4;
  var NORMAL_OPACITY = 0.9;
  var HIGHLIGHT_WEIGHT  = 7;
  var HIGHLIGHT_OPACITY = 1.0;
  var DIM_OPACITY = 0.25;

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

  Drupal.behaviors.raceLegMap = {
    attach: function (context) {
      once('race-leg-map', '.race-leg-map[data-legs]', context).forEach(function (el) {
        var legs;
        try {
          legs = JSON.parse(el.getAttribute('data-legs') || '[]');
        }
        catch (e) {
          legs = [];
        }

        var legsWithPolylines = legs.filter(function (leg) {
          return leg.polyline && leg.polyline.length > 0;
        });

        if (!legsWithPolylines.length) {
          el.innerHTML = '<div class="race-leg-map__no-routes">No route data available for these legs.</div>';
          return;
        }

        if (typeof window.L === 'undefined') {
          el.innerHTML = '<div class="race-leg-map__no-routes">Map library unavailable.</div>';
          return;
        }

        var canvasEl = el.querySelector('.race-leg-map__canvas');
        if (!canvasEl) {
          return;
        }

        var map = window.L.map(canvasEl, {
          scrollWheelZoom: false,
          attributionControl: true
        });

        window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
          subdomains: 'abcd'
        }).addTo(map);

        var allBounds = [];

        // Keyed by leg_number — used by the table-row hover behavior.
        var polylinesByLeg = {};

        legsWithPolylines.forEach(function (leg, i) {
          var color = LEG_COLORS[i % LEG_COLORS.length];
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

          var line = window.L.polyline(points, {
            color: color,
            weight: NORMAL_WEIGHT,
            opacity: NORMAL_OPACITY
          }).addTo(map);

          var popupLabel = 'Leg ' + leg.leg_number + ': ' + leg.label;
          if (leg.distance) {
            popupLabel += ' (' + parseFloat(leg.distance).toFixed(2) + ' mi)';
          }
          line.bindTooltip(popupLabel, { sticky: true });

          polylinesByLeg[leg.leg_number] = line;
          allBounds.push(line.getBounds());
        });

        if (allBounds.length) {
          var combined = allBounds[0];
          for (var j = 1; j < allBounds.length; j++) {
            combined = combined.extend(allBounds[j]);
          }
          map.fitBounds(combined, { padding: [20, 20] });
        }

        if (el.getAttribute('data-hide-legend') !== 'true') {
          var legendEl = document.createElement('div');
          legendEl.className = 'race-leg-map__legend';
          legsWithPolylines.forEach(function (leg, i) {
            var color = LEG_COLORS[i % LEG_COLORS.length];
            var item = document.createElement('span');
            item.className = 'race-leg-map__legend-item';
            item.innerHTML =
              '<span class="race-leg-map__legend-swatch" style="background:' + color + '"></span>' +
              'Leg ' + leg.leg_number + ': ' + leg.label;
            legendEl.appendChild(item);
          });
          el.appendChild(legendEl);
        }

        // Expose the polyline registry on the element so the table-row hover
        // behavior can reach it without a global variable.
        el._raceLegPolylines = polylinesByLeg;

        // Listen for highlight/reset events dispatched by the table behavior.
        el.addEventListener('race-leg-highlight', function (e) {
          var legNumber = e.detail.legNumber;
          Object.keys(polylinesByLeg).forEach(function (num) {
            var line = polylinesByLeg[num];
            if (String(num) === String(legNumber)) {
              line.setStyle({ weight: HIGHLIGHT_WEIGHT, opacity: HIGHLIGHT_OPACITY });
              line.bringToFront();
            }
            else {
              line.setStyle({ opacity: DIM_OPACITY });
            }
          });
        });

        el.addEventListener('race-leg-reset', function () {
          Object.keys(polylinesByLeg).forEach(function (num) {
            polylinesByLeg[num].setStyle({ weight: NORMAL_WEIGHT, opacity: NORMAL_OPACITY });
          });
        });
      });

      // Table row hover — find any table on the page that has data-leg-number rows.
      once('race-leg-table-hover', 'body', context).forEach(function () {
        var mapEl = document.querySelector('.race-leg-map[data-legs]');
        if (!mapEl) {
          return;
        }

        // Use event delegation on the document so it works regardless of
        // which wrapper class the view uses or whether it loads via AJAX.
        document.addEventListener('mouseover', function (e) {
          var row = e.target.closest('tr[data-leg-number]');
          if (!row) {
            return;
          }
          mapEl.dispatchEvent(new CustomEvent('race-leg-highlight', {
            detail: { legNumber: row.getAttribute('data-leg-number') }
          }));
        });

        document.addEventListener('mouseout', function (e) {
          var row = e.target.closest('tr[data-leg-number]');
          if (!row) {
            return;
          }
          // Only reset when actually leaving the row (not moving between child elements).
          var related = e.relatedTarget;
          if (related && row.contains(related)) {
            return;
          }
          mapEl.dispatchEvent(new CustomEvent('race-leg-reset'));
        });
      });
    }
  };
})(Drupal, once);
