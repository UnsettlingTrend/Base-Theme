/**
 * @file
 * COVE Editor widget behavior for the UT Data Visualization module.
 *
 * Manages the lifecycle of the CDC COVE editor inside a modal dialog.
 * When the "Open COVE Editor" button is clicked, a full-screen modal dialog
 * is created, the COVE editor React application is dynamically loaded from
 * CDN and mounted into the modal. When the user finishes editing and clicks
 * "Save", the resulting JSON configuration is serialised back into the hidden
 * textarea so Drupal captures it on form submission.
 *
 * COVE packages used:
 * - @cdc/editor: The main editor UI (includes chart, map, dashboard editors).
 * - React & ReactDOM: Peer dependencies loaded from CDN.
 *
 * @see CoveEditorWidget.php
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Ensures the local COVE bundle globals (React, ReactDOM, CdcEditor) are
   * available. The bundle (cove-editor-bundle.js) is loaded as a Drupal
   * library dependency and exposes these on `window` before this script runs.
   *
   * @param {Function} callback - Called when globals are confirmed available.
   */
  function ensureCdnLoaded(callback) {
    if (window.React && window.ReactDOM && (window.CdcEditor || window.CoveEditor || (window.CDC && window.CDC.Editor))) {
      callback();
    } else {
      console.error(
        '[COVE Editor] COVE bundle globals not found on window. ' +
        'Ensure cove-editor-bundle.js is loaded before cove-editor.js.'
      );
    }
  }

  /**
   * Creates and opens the COVE editor modal dialog.
   *
   * The modal is a simple full-viewport overlay with a mount point for the
   * React editor, plus Save and Cancel buttons in a toolbar. When the user
   * clicks Save, the current editor state is serialised to JSON and written
   * back into the hidden textarea identified by `delta`.
   *
   * @param {number} delta
   *   The field delta index, used to target the correct hidden textarea.
   * @param {string} currentValue
   *   The current JSON configuration string (may be empty for new visualizations).
   */
  function openEditorModal(delta, currentValue) {
    // Build the modal overlay.
    var overlay = document.createElement('div');
    overlay.className = 'cove-modal-overlay';
    overlay.innerHTML =
      '<div class="cove-modal">' +
        '<div class="cove-modal__toolbar">' +
          '<h2 class="cove-modal__title">COVE Data Visualization Editor</h2>' +
          '<div class="cove-modal__actions">' +
            '<button type="button" class="cove-modal__save button button--primary">Save Configuration</button>' +
            '<button type="button" class="cove-modal__cancel button">Cancel</button>' +
          '</div>' +
        '</div>' +
        '<div class="cove-modal__body">' +
          '<div class="cove-modal__editor-mount"></div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);
    document.body.classList.add('cove-modal-open');

    var mountPoint = overlay.querySelector('.cove-modal__editor-mount');
    var saveBtn = overlay.querySelector('.cove-modal__save');
    var cancelBtn = overlay.querySelector('.cove-modal__cancel');

    // Track the latest config from the editor.
    // For a brand-new visualization (empty field), pass undefined so the COVE
    // editor enters its "new viz" flow (sets newViz: true internally).
    var parsedConfig;
    try {
      parsedConfig = currentValue ? JSON.parse(currentValue) : undefined;
      // Treat an empty object as a new visualization too.
      if (parsedConfig && Object.keys(parsedConfig).length === 0) {
        parsedConfig = undefined;
      }
    } catch (e) {
      parsedConfig = undefined;
    }
    var latestConfig = parsedConfig || {};

    // Mount the COVE editor React component into the modal.
    ensureCdnLoaded(function () {
      try {
        // The COVE editor exposes itself on window as CdcEditor or similar.
        var EditorComponent = window.CdcEditor || window.CoveEditor ||
          (window.CDC && window.CDC.Editor);

        if (EditorComponent) {
          // Listen for config updates emitted by the COVE editor via
          // CustomEvent('updateVizConfig'). This is how @cdc/editor
          // communicates state changes (not via an onChange prop).
          var configHandler = function (e) {
            try {
              latestConfig = typeof e.detail === 'string'
                ? JSON.parse(e.detail)
                : e.detail;
            } catch (err) {
              // Keep the last known good config.
            }
          };
          window.addEventListener('updateVizConfig', configHandler);
          overlay._configHandler = configHandler;

          // Mount using React 18's createRoot API.
          var root = window.ReactDOM.createRoot(mountPoint);
          root.render(
            window.React.createElement(EditorComponent, {
              config: parsedConfig,
            })
          );

          // Store root reference for cleanup.
          overlay._reactRoot = root;
        } else {
          // COVE editor global not found — fall back to a JSON textarea editor.
          console.warn(
            '[COVE Editor] COVE editor component not found on window. ' +
            'Falling back to manual JSON editing.'
          );
          renderFallbackEditor(mountPoint, currentValue, function (json) {
            try { latestConfig = json ? JSON.parse(json) : {}; } catch (e) { /* keep last */ }
          });
        }
      } catch (err) {
        console.error('[COVE Editor] Failed to mount editor:', err);
        renderFallbackEditor(mountPoint, currentValue, function (json) {
          try { latestConfig = json ? JSON.parse(json) : {}; } catch (e) { /* keep last */ }
        });
      }
    });

    // Save button: write the config back to the hidden textarea.
    saveBtn.addEventListener('click', function () {
      var jsonString = JSON.stringify(latestConfig, null, 2);
      var textarea = document.querySelector(
        '.cove-editor-json-value[data-cove-delta="' + delta + '"]'
      );
      if (textarea) {
        // Update the textarea value and trigger a change event so Drupal
        // recognises the form has been modified.
        textarea.value = jsonString;
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
      }

      // Update the status indicator.
      updateStatus(delta, true);

      // Update the preview.
      updatePreview(delta, jsonString);

      closeModal(overlay);
    });

    // Cancel button: close without saving.
    cancelBtn.addEventListener('click', function () {
      closeModal(overlay);
    });

    // Close on Escape key.
    overlay.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeModal(overlay);
      }
    });
  }

  /**
   * Renders a fallback JSON textarea editor when the COVE React component
   * is unavailable (e.g. CDN failure or bundle mismatch).
   *
   * @param {HTMLElement} mountPoint - The DOM element to render into.
   * @param {string} currentValue - The current JSON string.
   * @param {Function} onChange - Callback invoked with the updated JSON string.
   */
  function renderFallbackEditor(mountPoint, currentValue, onChange) {
    var formatted = '';
    try {
      formatted = JSON.stringify(JSON.parse(currentValue), null, 2);
    } catch (e) {
      formatted = currentValue || '{}';
    }

    mountPoint.innerHTML =
      '<div class="cove-fallback-editor">' +
        '<p class="cove-fallback-editor__notice">' +
          '<strong>Note:</strong> The COVE visual editor could not be loaded. ' +
          'You can edit the JSON configuration directly below.' +
        '</p>' +
        '<textarea class="cove-fallback-editor__textarea">' +
          Drupal.checkPlain(formatted) +
        '</textarea>' +
      '</div>';

    var textarea = mountPoint.querySelector('.cove-fallback-editor__textarea');
    textarea.addEventListener('input', function () {
      onChange(textarea.value);
    });
  }

  /**
   * Closes and removes the COVE editor modal from the DOM.
   *
   * Unmounts any React root that was created inside the modal to prevent
   * memory leaks.
   *
   * @param {HTMLElement} overlay - The modal overlay element.
   */
  function closeModal(overlay) {
    // Remove the config update event listener if present.
    if (overlay._configHandler) {
      window.removeEventListener('updateVizConfig', overlay._configHandler);
    }
    // Unmount React root if present.
    if (overlay._reactRoot) {
      try {
        overlay._reactRoot.unmount();
      } catch (e) {
        // Ignore unmount errors.
      }
    }
    document.body.classList.remove('cove-modal-open');
    overlay.remove();
  }

  /**
   * Updates the status indicator for a given field delta.
   *
   * @param {number} delta - The field delta.
   * @param {boolean} hasConfig - Whether a configuration is present.
   */
  function updateStatus(delta, hasConfig) {
    var statusEl = document.querySelector(
      '.cove-editor-status[data-cove-delta="' + delta + '"]'
    );
    if (statusEl) {
      statusEl.innerHTML = hasConfig
        ? '<span class="cove-editor-status__configured">✔ ' + Drupal.t('Visualization configured') + '</span>'
        : '<span class="cove-editor-status__empty">' + Drupal.t('No visualization configured yet.') + '</span>';
    }

    // Update the button label.
    var button = document.querySelector(
      '.cove-editor-open-button[data-cove-delta="' + delta + '"]'
    );
    if (button) {
      button.value = hasConfig
        ? Drupal.t('Edit Visualization')
        : Drupal.t('Create Visualization');
    }
  }

  /**
   * Updates the in-widget preview for a given field delta.
   *
   * Triggers the COVE renderer behavior on the preview container so the user
   * can see what their configuration looks like without leaving the edit form.
   *
   * @param {number} delta - The field delta.
   * @param {string} jsonString - The JSON configuration string.
   */
  function updatePreview(delta, jsonString) {
    var previewEl = document.querySelector(
      '[data-cove-preview-delta="' + delta + '"]'
    );
    if (!previewEl || !jsonString || jsonString === '{}') {
      return;
    }

    // Set the data attribute so the renderer behavior can pick it up.
    previewEl.setAttribute('data-cove-config', jsonString);
    previewEl.setAttribute('data-cove-id', 'cove-preview-' + delta);
    previewEl.innerHTML =
      '<div class="cove-visualization__loading">' +
        Drupal.t('Loading preview…') +
      '</div>';

    // Re-attach the renderer behavior to process the new preview element.
    if (Drupal.behaviors.coveRenderer) {
      Drupal.behaviors.coveRenderer.attach(previewEl, drupalSettings);
    }
  }

  /**
   * Drupal behavior: attaches click handlers to COVE editor open buttons.
   *
   * Uses `once()` to ensure each button is only processed a single time,
   * even when Drupal re-attaches behaviors (e.g. after AJAX operations).
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.coveEditor = {
    attach: function (context) {
      once('cove-editor', '.cove-editor-open-button', context).forEach(
        function (button) {
          button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var delta = button.getAttribute('data-cove-delta');
            var textarea = document.querySelector(
              '.cove-editor-json-value[data-cove-delta="' + delta + '"]'
            );
            var currentValue = textarea ? textarea.value : '';

            openEditorModal(parseInt(delta, 10), currentValue);
          });
        }
      );
    },
  };

})(Drupal, once);

