(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.runnerAssignment = {
    attach(context) {
      once('runner-assignment-delegate', 'body', context).forEach(function () {
        // Use capture phase (third arg = true) so our handler fires before
        // anything on the page can call stopPropagation() and swallow the event.
        document.addEventListener('click', function (e) {
          var removeBtn = e.target.closest('.runner-remove-btn');
          if (removeBtn && !removeBtn.disabled) {
            e.preventDefault();
            e.stopPropagation();
            removeBtn.disabled = true;
            postAndProcess(removeBtn.dataset.url, {
              paragraph_id: removeBtn.dataset.paragraphId,
              csrf_token: removeBtn.dataset.csrfToken,
            }).finally(function () {
              removeBtn.disabled = false;
            });
            return;
          }

          var assignMe = e.target.closest('.runner-assign-me');
          if (assignMe) {
            e.preventDefault();
            e.stopPropagation();
            assignMe.style.pointerEvents = 'none';
            postAndProcess(assignMe.dataset.url, {
              paragraph_id: assignMe.dataset.paragraphId,
              user_id: assignMe.dataset.userId,
              csrf_token: assignMe.dataset.csrfToken,
            }).finally(function () {
              assignMe.style.pointerEvents = '';
            });
          }
        }, true);

        document.addEventListener('change', function (e) {
          var select = e.target.closest('.runner-assign-select');
          if (select && select.value) {
            select.disabled = true;
            postAndProcess(select.dataset.url, {
              paragraph_id: select.dataset.paragraphId,
              user_id: select.value,
              csrf_token: select.dataset.csrfToken,
            }).finally(function () {
              select.disabled = false;
            });
          }
        });
      });
    },
  };

  function postAndProcess(url, data) {
    // Create a detached Drupal Ajax object so we can use its built-in
    // success() handler, which has getEffect() and all other methods that
    // command implementations (insert, message, invoke, etc.) depend on.
    var ajax = Drupal.ajax({ url: url, progress: false });

    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: new URLSearchParams(data).toString(),
    })
    .then(function (response) {
      if (!response.ok) {
        console.error('Runner assignment request failed:', response.status);
        return;
      }
      return response.json();
    })
    .then(function (commands) {
      if (commands) {
        ajax.success(commands, 200);
      }
    })
    .catch(function (err) {
      console.error('Runner assignment error:', err);
    });
  }

})(Drupal, once);
