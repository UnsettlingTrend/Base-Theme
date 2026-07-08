(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.raceTeamRequestStatusReview = {
    attach(context) {
      once('request-status-review', '.request-status-select', context).forEach(function (select) {
        select.addEventListener('change', function () {
          const sid = select.dataset.sid;
          const status = select.value;
          const endpoint = drupalSettings.race_day.requestStatusUrl;

          select.disabled = true;

          // Fetch session CSRF token then POST the update.
          fetch('/session/token')
            .then(function (r) { return r.text(); })
            .then(function (token) {
              return fetch(endpoint, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded',
                  'X-CSRF-Token': token,
                },
                body: 'sid=' + encodeURIComponent(sid) + '&status=' + encodeURIComponent(status),
              });
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
              if (data.error) {
                Drupal.announce(Drupal.t('Error updating status: @error', { '@error': data.error }));
                console.error('Request status update error:', data.error);
              }
            })
            .catch(function (err) {
              Drupal.announce(Drupal.t('Network error updating status.'));
              console.error('Request status update network error:', err);
            })
            .finally(function () {
              select.disabled = false;
            });
        });
      });
    },
  };
}(Drupal, once, drupalSettings));
