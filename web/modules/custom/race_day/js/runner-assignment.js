(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.runnerAssignment = {
    attach(context) {
      once('runner-assign-me', '.runner-assign-me', context).forEach(function (link) {
        link.addEventListener('click', function (e) {
          e.preventDefault();
          submitAssignment(link, link.dataset.url, link.dataset.paragraphId, link.dataset.userId, link.dataset.csrfToken);
        });
      });

      once('runner-assign-select', '.runner-assign-select', context).forEach(function (select) {
        select.addEventListener('change', function () {
          if (!select.value) {
            return;
          }
          submitAssignment(select, select.dataset.url, select.dataset.paragraphId, select.value, select.dataset.csrfToken);
        });
      });
    },
  };

  function submitAssignment(triggeringElement, url, paragraphId, userId, csrfToken) {
    triggeringElement.disabled = true;

    const ajax = Drupal.ajax({
      url: url,
      method: 'POST',
      submit: {
        paragraph_id: paragraphId,
        user_id: userId,
        csrf_token: csrfToken,
      },
      element: triggeringElement,
    });

    ajax.execute().then(function () {
      triggeringElement.disabled = false;
    });
  }

})(Drupal, once);
