(function ($, Drupal, once) {
  "use strict";
  Drupal.behaviors.toggleSearchBlock = {
    attach (context, settings) {
      once('toggleSearchBlock', 'html').forEach(function (element) {
        // Your jQuery code here
        $('#main-search-button').click(function () {
          $('#block-ut-base-searchapiform').toggle();
        });
      });
    }
  };
})(jQuery, Drupal, once);
