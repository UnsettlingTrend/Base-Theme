/**
 * @file
 * Renders all race leg polylines on a single shared Leaflet map.
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
            weight: 4,
            opacity: 0.9
          }).addTo(map);

          var popupLabel = 'Leg ' + leg.leg_number + ': ' + leg.label;
          if (leg.distance) {
            popupLabel += ' (' + parseFloat(leg.distance).toFixed(2) + ' mi)';
          }
          if (leg.difficulty) {
            popupLabel += ' &mdash; ' + leg.difficulty;
          }
          line.bindTooltip(popupLabel, { sticky: true });

          allBounds.push(line.getBounds());
        });

        if (allBounds.length) {
          var combined = allBounds[0];
          for (var j = 1; j < allBounds.length; j++) {
            combined = combined.extend(allBounds[j]);
          }
          map.fitBounds(combined, { padding: [20, 20] });
        }

        // Build legend below the map canvas.
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
      });
    }
  };
})(Drupal, once);
