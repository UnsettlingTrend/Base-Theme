Drupal.AjaxCommands.prototype.CryptoCurrencyUpdateCommand = function(ajax, response)
{
  $modal.find('.modal-content').first().html(response.form);
  $modal.foundation('open');
  Drupal.attachBehaviors($('#modal')[0], settings);
};