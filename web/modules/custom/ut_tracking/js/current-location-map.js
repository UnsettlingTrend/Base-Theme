/**
 * @file
 * Renders a single-pin Leaflet map for a user's current GPS location.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.utTrackingCurrentLocationMap = {
    attach: function (context) {
      once('ut-tracking-current-location-map', '.ut-tracking-current-location-map[data-lat]', context).forEach(function (el) {
        var lat = parseFloat(el.getAttribute('data-lat'));
        var lng = parseFloat(el.getAttribute('data-lng'));
        if (isNaN(lat) || isNaN(lng) || typeof window.L === 'undefined') {
          return;
        }

        el.innerHTML = '';
        var mapEl = document.createElement('div');
        mapEl.className = 'ut-tracking-current-location-map__canvas';
        el.appendChild(mapEl);

        var map = window.L.map(mapEl, {
          scrollWheelZoom: false,
          attributionControl: true
        }).setView([lat, lng], 14);

        window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
          maxZoom: 19,
          attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
          subdomains: 'abcd'
        }).addTo(map);

        window.L.marker([lat, lng]).addTo(map);
      });
    }
  };
})(Drupal, once);
