/**
 * @file
 * Renders Strava route maps from saved polyline data.
 */
(function (Drupal, once) {
  'use strict';

  function decodePolyline(encoded) {
    var points = [];
    var index = 0;
    var lat = 0;
    var lng = 0;

    while (index < encoded.length) {
      var b;
      var shift = 0;
      var result = 0;
      do {
        b = encoded.charCodeAt(index++) - 63;
        result |= (b & 0x1f) << shift;
        shift += 5;
      } while (b >= 0x20);
      var dlat = (result & 1) ? ~(result >> 1) : (result >> 1);
      lat += dlat;

      shift = 0;
      result = 0;
      do {
        b = encoded.charCodeAt(index++) - 63;
        result |= (b & 0x1f) << shift;
        shift += 5;
      } while (b >= 0x20);
      var dlng = (result & 1) ? ~(result >> 1) : (result >> 1);
      lng += dlng;

      points.push([lat / 1e5, lng / 1e5]);
    }

    return points;
  }

  function renderFallback(el, routeUrl) {
    var html = '<div class="strava-route-map--error">Route map data is not available.';
    if (routeUrl) {
      html += ' <a href="' + routeUrl + '" target="_blank" rel="noopener noreferrer">View on Strava</a>';
    }
    html += '</div>';
    el.innerHTML = html;
  }

  Drupal.behaviors.stravaRouteMap = {
    attach: function (context) {
      once('strava-route-map', '.strava-route-map[data-strava-route-id]', context).forEach(function (el) {
        var routeId = el.getAttribute('data-strava-route-id') || '';
        var routeUrl = el.getAttribute('data-strava-route-url') || (routeId ? ('https://www.strava.com/routes/' + encodeURIComponent(routeId)) : '');
        var polyline = el.getAttribute('data-strava-polyline') || el.getAttribute('data-strava-summary-polyline') || '';
        var mapHeight = el.getAttribute('data-strava-map-height') || '500px';
        if (!routeId || !polyline) {
          renderFallback(el, routeUrl);
          return;
        }

        if (typeof window.L === 'undefined') {
          renderFallback(el, routeUrl);
          return;
        }

        var points;
        try {
          points = decodePolyline(polyline);
        }
        catch (e) {
          renderFallback(el, routeUrl);
          return;
        }

        if (!points.length) {
          renderFallback(el, routeUrl);
          return;
        }

        el.innerHTML = '';
        var mapEl = document.createElement('div');
        mapEl.className = 'strava-route-map__canvas';
        mapEl.style.height = mapHeight;
        el.appendChild(mapEl);

        var map = window.L.map(mapEl, {
          scrollWheelZoom: false,
          attributionControl: true
        });

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var line = window.L.polyline(points, {
          color: '#fc4c02',
          weight: 4,
          opacity: 0.9
        }).addTo(map);

        map.fitBounds(line.getBounds(), {
          padding: [20, 20]
        });
      });
    }
  };
})(Drupal, once);
