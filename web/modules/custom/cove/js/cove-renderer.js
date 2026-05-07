/**
 * @file
 * COVE Renderer behavior for the UT Data Visualization module.
 *
 * Scans the page for elements with a `data-cove-config` attribute and mounts
 * the appropriate CDC COVE React component (chart, map, dashboard, etc.) into
 * each one. The COVE packages are bundled locally in
 * js/vendor/cove-renderer-bundle.js which exposes React, ReactDOM, and all
 * COVE component globals on window.
 *
 * @see CoveVisualizationFormatter.php
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Tracks React roots created for each visualization element, keyed by
   * the `data-cove-id` attribute.
   *
   * @type {Map<string, Object>}
   */
  const activeRoots = new Map();

  /**
   * Maps a COVE configuration type string to the expected global variable
   * name on `window`.
   *
   * @param {string} type - The visualization type from the JSON config.
   * @return {string|null} The global name, or null if unsupported.
   */
  function resolveComponentGlobal(type) {
    var typeMap = {
      'chart':           'CdcChart',
      'Bar':             'CdcChart',
      'Line':            'CdcChart',
      'Pie':             'CdcChart',
      'map':             'CdcMap',
      'us-county':       'CdcMap',
      'us':              'CdcMap',
      'world':           'CdcMap',
      'single-state':    'CdcMap',
      'dashboard':       'CdcDashboard',
      'data-bite':       'CdcDataBite',
      'waffle-chart':    'CdcWaffleChart',
      'markup-include':  'CdcMarkupInclude',
    };

    return typeMap[type] || 'CdcChart'; // Default to chart.
  }

  /**
   * Mounts a COVE visualization component into a DOM element.
   *
   * @param {HTMLElement} el - The container element to render into.
   * @param {Object} config - The parsed COVE JSON configuration object.
   */
  function mountVisualization(el, config) {
    var vizType = config.type
      || config.visualizationType
      || (config.general && config.general.type)
      || 'chart';

    var globalName = resolveComponentGlobal(vizType);
    var Component = window[globalName];

    if (!Component) {
      el.innerHTML =
        '<p class="cove-visualization--error">' +
          Drupal.t('Visualization component "@type" could not be loaded.', {
            '@type': vizType,
          }) +
        '</p>';
      return;
    }

    // Create a React 18 root and render the component.
    var root = window.ReactDOM.createRoot(el);
    root.render(
      window.React.createElement(Component, { config: config })
    );

    // Track the root for cleanup on detach.
    var coveId = el.getAttribute('data-cove-id');
    if (coveId) {
      activeRoots.set(coveId, root);
    }
  }

  /**
   * Drupal behavior: mounts COVE visualizations on elements with
   * `data-cove-config` attributes.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.coveRenderer = {
    /**
     * Attach: finds all unprocessed COVE visualization containers, parses
     * their JSON config, and mounts the appropriate React component.
     */
    attach: function (context) {
      once('cove-renderer', '[data-cove-config]', context).forEach(
        function (el) {
          var jsonString = el.getAttribute('data-cove-config');
          if (!jsonString) {
            return;
          }

          try {
            var config = JSON.parse(jsonString);
            mountVisualization(el, config);
          } catch (err) {
            console.error('[COVE Renderer] Invalid JSON config:', err);
            el.innerHTML =
              '<p class="cove-visualization--error">' +
                Drupal.t('Invalid visualization configuration.') +
              '</p>';
          }
        }
      );
    },

    /**
     * Detach: unmounts React roots when elements are removed from the DOM
     * (e.g. during AJAX replacement or page navigation).
     */
    detach: function (context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      var elements = context.querySelectorAll
        ? context.querySelectorAll('[data-cove-id]')
        : [];

      elements.forEach(function (el) {
        var coveId = el.getAttribute('data-cove-id');
        if (coveId && activeRoots.has(coveId)) {
          try {
            activeRoots.get(coveId).unmount();
          } catch (e) {
            // Ignore unmount errors during teardown.
          }
          activeRoots.delete(coveId);
        }
      });
    },
  };

})(Drupal, once);

