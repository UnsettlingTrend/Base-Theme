(function ($) {
  Drupal.behaviors.myModuleBehavior = {
    attach: function (context, settings) {
      // Your jQuery code here
      $('.my-element').click(function () {
        alert('You clicked the element!');
      });
    }
  };
})(jQuery);
