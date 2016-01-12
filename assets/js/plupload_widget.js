(function ($) {

  Drupal.plupload_widget = Drupal.plupload_widget || {};

  // Add Plupload events for autoupload and autosubmit.
  Drupal.plupload_widget.filesAddedCallback = function (up, files) {
    setTimeout(function () { up.start() }, 100);
  };
   
 
  Drupal.plupload_widget.uploadCompleteCallback = function (up, files) {
    var $this = $("#" + up.settings.container);
    var $container = $this.closest('.form-managed-file');
    // If there is submit_element trigger it.
    var submit_element = drupalSettings.plupload[$this.attr('id')].submit_element;
    if (submit_element) {
      // Trigger the upload button.
      $button = $container.find(submit_element);
      $event = drupalSettings['ajax'][$button.attr('id')]['event'];
      $button.trigger($event);
      // Now hide the uploader...
      // the ajax throbber will show.
      $(up).hide();

      return;

      // This was used in an attempt to provide multiple
      // simultaneos uploads without refreshing the widget..
      $.each(up.files, function (i, file) {
        if (file && file.id == files[0].id) {
          up.removeFile(file);
        }
      });
    }
  };

})(jQuery);
