(function ($) {

  Drupal.plupload_widget = Drupal.plupload_widget || {};

  // Add Plupload events for autoupload and autosubmit.
  Drupal.plupload_widget.filesAddedCallback = function (up, files) {
    setTimeout(function () { up.start(); }, 100);
  };

  Drupal.plupload_widget.uploadCompleteCallback = function (up, files) {
    $(up.settings.container)
      .closest('form')
      .find('#edit-submit')
      .click()
  };

})(jQuery);
