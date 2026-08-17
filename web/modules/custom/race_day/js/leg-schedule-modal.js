(function (Drupal, once) {
  'use strict';

  // Attached to 'body' rather than '.leg-schedule-modal' directly: the modal
  // is inserted via Drupal's AJAX dialog, which can call attachBehaviors()
  // with the modal's own root element as the context — and once()'s
  // querySelectorAll-based matching never matches the context element
  // itself, only descendants. 'body' will always be a descendant of
  // whatever context is passed here, so the delegated listener below always
  // attaches exactly once, regardless of how the modal content was scoped.
  // Mirrors race_day/runner_assignment's proven pattern (js/runner-assignment.js).
  Drupal.behaviors.legScheduleModal = {
    attach(context) {
      once('leg-schedule-modal', 'body', context).forEach(function () {
        document.addEventListener('click', function (e) {
          var modal = e.target.closest('.leg-schedule-modal');
          if (!modal) {
            return;
          }

          var editBtn = e.target.closest('.leg-schedule-edit-btn');
          if (editBtn) {
            var editRow = editBtn.closest('.leg-schedule-row');
            editRow.querySelector('.leg-schedule-edit-form').hidden = false;
            editRow.querySelector('.leg-schedule-actual-actions').hidden = true;
            return;
          }

          var cancelBtn = e.target.closest('.leg-schedule-cancel-btn');
          if (cancelBtn) {
            var cancelRow = cancelBtn.closest('.leg-schedule-row');
            cancelRow.querySelector('.leg-schedule-edit-form').hidden = true;
            cancelRow.querySelector('.leg-schedule-actual-actions').hidden = false;
            cancelRow.querySelector('.leg-schedule-error').textContent = '';
            return;
          }

          var saveBtn = e.target.closest('.leg-schedule-save-btn');
          if (saveBtn) {
            var row = saveBtn.closest('.leg-schedule-row');
            var input = row.querySelector('.leg-schedule-input');
            postUpdate(modal, row, saveBtn, 'field=' + encodeURIComponent(row.dataset.field) + '&value=' + encodeURIComponent(input.value));
            return;
          }

          var resetBtn = e.target.closest('.leg-schedule-reset-btn');
          if (resetBtn) {
            var resetRow = resetBtn.closest('.leg-schedule-row');
            if (!window.confirm('Reset ' + resetBtn.dataset.label + '? This clears the recorded actual value.')) {
              return;
            }
            postUpdate(modal, resetRow, resetBtn, 'field=' + encodeURIComponent(resetBtn.dataset.field) + '&reset=1');
          }
        }, true);

        // Drupal's dialog title is always plain text — core strips any HTML
        // out of it unconditionally (OpenDialogCommand::__construct()), so
        // the leg name/runner name sub-lines can't be delivered via #title
        // at all. Rebuilt here instead, once the dialog chrome actually
        // exists (dialog:aftercreate, fired by Drupal.dialog() right after
        // jQuery UI builds .ui-dialog-titlebar — attachBehaviors() runs too
        // early for this, since it fires on the inserted content before
        // Drupal.dialog() wraps it in that chrome). Only relevant on
        // initial open, not on the Save/Reset ReplaceCommand refreshes
        // above — those don't recreate the dialog, and the leg/runner don't
        // change from a time edit anyway.
        document.addEventListener('dialog:aftercreate', function (e) {
          if (!e.target.querySelector) {
            return;
          }
          var modal = e.target.querySelector('.leg-schedule-modal');
          if (!modal) {
            return;
          }
          var dialogWrapper = e.target.closest('.ui-dialog');
          var titleEl = dialogWrapper ? dialogWrapper.querySelector('.ui-dialog-title') : null;
          if (!titleEl) {
            return;
          }
          var legName = modal.dataset.legName;
          var runnerName = modal.dataset.runnerName;
          var html = '<span class="leg-schedule-title-main">' + titleEl.textContent + '</span>';
          if (legName) {
            html += '<span class="leg-schedule-title-sub">' + Drupal.checkPlain(legName) + '</span>';
          }
          if (runnerName) {
            html += '<span class="leg-schedule-title-sub">' + Drupal.checkPlain(runnerName) + '</span>';
          }
          titleEl.innerHTML = html;
        });
      });
    },
  };

  /**
   * POSTs a save or reset request for one row and applies the ReplaceCommands
   * the endpoint returns for both the modal and the Runner Assignments table.
   */
  function postUpdate(modal, row, triggerBtn, body) {
    var errorEl = row.querySelector('.leg-schedule-error');
    var updateUrl = modal.dataset.updateUrl;

    triggerBtn.disabled = true;
    errorEl.textContent = '';

    // Detached Drupal.ajax object so its success() handler applies the
    // ReplaceCommands the endpoint returns.
    var ajax = Drupal.ajax({ url: updateUrl, progress: false });

    fetch('/session/token')
      .then(function (r) { return r.text(); })
      .then(function (token) {
        return fetch(updateUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': token,
          },
          body: body,
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
        ajax.success(commands, 200);
      })
      .catch(function (err) {
        errorEl.textContent = err.message;
        triggerBtn.disabled = false;
      });
  }

})(Drupal, once);
