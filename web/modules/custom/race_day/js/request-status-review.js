(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.raceTeamRequestStatusReview = {
    attach(context) {
      once('request-status-review', '.request-status-select', context).forEach(function (select) {
        select.addEventListener('change', function () {
          const sid = select.dataset.sid;
          const status = select.value;
          const endpoint = drupalSettings.race_day.requestStatusUrl;
          const previous = select.dataset.previous || select.querySelector('option[selected]')?.value || '';

          // Store the previous value for rollback on error.
          select.dataset.previous = previous;
          select.disabled = true;
          select.classList.remove('request-status--error');

          // Detached Drupal.ajax object so its success() handler (with
          // getEffect() etc.) can apply the ReplaceCommands the endpoint
          // returns for the Runner Assignments and Team Members blocks.
          const ajax = Drupal.ajax({ url: endpoint, progress: false });

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
            .then(function (response) {
              if (!response.ok) {
                return response.json().then(function (data) {
                  throw new Error(data.error || ('HTTP ' + response.status));
                });
              }
              return response.json();
            })
            .then(function (commands) {
              // Success — record new value as the rollback baseline.
              select.dataset.previous = status;
              ajax.success(commands, 200);
            })
            .catch(function (err) {
              // Roll back the select to the previous value and mark it as errored.
              select.value = select.dataset.previous;
              select.classList.add('request-status--error');
              select.title = Drupal.t('Save failed: @err', { '@err': err.message });
              console.error('Request status update error:', err);
            })
            .finally(function () {
              select.disabled = false;
            });
        });
      });
    },
  };
}(Drupal, once, drupalSettings));
