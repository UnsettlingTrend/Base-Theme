(function (Drupal, once) {
  'use strict';

  // Tracks paragraph_ids with an assign/remove request currently in flight,
  // so a second interaction on the same leg — a real double-click, or a
  // stale control from just before a table replace — can't race the first
  // one. That race is what let a leg get rejected as "already assigned":
  // the second, losing request's response carries only a message command,
  // no table update, so without this guard the table silently doesn't
  // reflect what the user just did and there was nothing visible telling
  // them why.
  var pending = {};

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
            var removeParagraphId = removeBtn.dataset.paragraphId;
            if (pending[removeParagraphId]) {
              return;
            }
            pending[removeParagraphId] = true;
            showError(removeParagraphId, '');
            removeBtn.disabled = true;
            postAndProcess(removeBtn.dataset.url, {
              paragraph_id: removeParagraphId,
              csrf_token: removeBtn.dataset.csrfToken,
            }, removeParagraphId).finally(function () {
              removeBtn.disabled = false;
              delete pending[removeParagraphId];
            });
            return;
          }

          var assignMe = e.target.closest('.runner-assign-me');
          if (assignMe) {
            e.preventDefault();
            e.stopPropagation();
            var assignMeParagraphId = assignMe.dataset.paragraphId;
            if (pending[assignMeParagraphId]) {
              return;
            }
            pending[assignMeParagraphId] = true;
            showError(assignMeParagraphId, '');
            assignMe.style.pointerEvents = 'none';
            postAndProcess(assignMe.dataset.url, {
              paragraph_id: assignMeParagraphId,
              user_id: assignMe.dataset.userId,
              csrf_token: assignMe.dataset.csrfToken,
            }, assignMeParagraphId).finally(function () {
              assignMe.style.pointerEvents = '';
              delete pending[assignMeParagraphId];
            });
          }
        }, true);

        document.addEventListener('change', function (e) {
          var select = e.target.closest('.runner-assign-select');
          if (select && select.value) {
            var selectParagraphId = select.dataset.paragraphId;
            if (pending[selectParagraphId]) {
              select.value = '';
              return;
            }
            pending[selectParagraphId] = true;
            showError(selectParagraphId, '');
            select.disabled = true;
            postAndProcess(select.dataset.url, {
              paragraph_id: selectParagraphId,
              user_id: select.value,
              csrf_token: select.dataset.csrfToken,
            }, selectParagraphId).finally(function () {
              select.disabled = false;
              delete pending[selectParagraphId];
            });
          }
        });
      });
    },
  };

  /**
   * Shows (or, given '', clears) an inline error/warning next to the given
   * paragraph's assign/remove controls — querySelectorAll rather than a
   * single query in case a stale duplicate is ever briefly present across
   * a table replace.
   */
  function showError(paragraphId, message) {
    document.querySelectorAll('.runner-assignment-error[data-paragraph-id="' + paragraphId + '"]').forEach(function (el) {
      el.textContent = message;
      el.hidden = !message;
    });
  }

  /**
   * The whole table, not just the row that triggered a request — so a
   * click on a *different* leg can't fire a second overlapping request
   * while the first is still in flight either. The per-leg `pending` guard
   * above only blocks a second interaction on the *same* leg; this is the
   * broader "nothing in this table is clickable until the current request
   * settles" lock.
   */
  function lockTable() {
    var wrapper = document.querySelector('[id^="runner-assignments-"]');
    if (wrapper) {
      wrapper.classList.add('runner-assignments--locked');
    }
  }

  function unlockTable() {
    var wrapper = document.querySelector('[id^="runner-assignments-"]');
    if (wrapper) {
      wrapper.classList.remove('runner-assignments--locked');
    }
  }

  function postAndProcess(url, data, paragraphId) {
    lockTable();

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
        return response.json().catch(function () { return null; }).then(function (data) {
          throw new Error((data && data.error) || ('Request failed (HTTP ' + response.status + ').'));
        });
      }
      return response.json();
    })
    .then(function (commands) {
      if (!commands) {
        return;
      }
      // A successful assign/remove always includes a ReplaceCommand for the
      // table (see AssignRunnerController::reRenderTable()). If one isn't
      // present, the request didn't actually change anything — the "leg
      // already assigned" rejection is the one case that returns 200 with
      // only a message command — so surface that message right at this row
      // instead of leaving the user to notice (or not) a banner at the top
      // of a long page.
      var updatedTable = commands.some(function (c) { return c.command === 'insert' && c.method === 'replaceWith'; });
      if (!updatedTable) {
        var messageCommand = commands.find(function (c) { return c.command === 'message'; });
        if (messageCommand) {
          showError(paragraphId, messageCommand.message);
        }
      }
      ajax.success(commands, 200);
    })
    .catch(function (err) {
      showError(paragraphId, err.message);
      console.error('Runner assignment error:', err);
    })
    .finally(function () {
      // A successful update replaces #runner-assignments-{group} wholesale
      // (see reRenderTable()'s ReplaceCommand), so the locked class is
      // already gone along with everything else on that path — this only
      // has real work to do on the failure/rejection paths, where the old
      // (locked) table is still the one on the page. Unconditional either
      // way, since re-querying a table that's already been replaced is
      // harmless.
      unlockTable();
    });
  }

})(Drupal, once);
