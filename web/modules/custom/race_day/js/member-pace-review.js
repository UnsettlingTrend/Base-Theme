(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.raceTeamMemberPaceReview = {
    attach(context) {
      once('member-pace-review', '.member-pace-select', context).forEach(function (select) {
        select.addEventListener('change', function () {
          const relationshipId = select.dataset.relationshipId;
          const pace = select.value;
          const endpoint = drupalSettings.race_day.memberPaceUrl;
          const previous = select.dataset.previous || select.querySelector('option[selected]')?.value || '';

          // Store the previous value for rollback on error.
          select.dataset.previous = previous;
          select.disabled = true;
          select.classList.remove('member-pace--error');

          // Detached Drupal.ajax object so its success() handler applies the
          // ReplaceCommand the endpoint returns for the Runner Assignments block.
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
                body: 'relationship_id=' + encodeURIComponent(relationshipId) + '&pace=' + encodeURIComponent(pace),
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
              select.dataset.previous = pace;
              ajax.success(commands, 200);
            })
            .catch(function (err) {
              // Roll back the select to the previous value and mark it as errored.
              select.value = select.dataset.previous;
              select.classList.add('member-pace--error');
              select.title = Drupal.t('Save failed: @err', { '@err': err.message });
              console.error('Member pace update error:', err);
            })
            .finally(function () {
              select.disabled = false;
            });
        });
      });
    },
  };
}(Drupal, once, drupalSettings));
