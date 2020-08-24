(function ($) {

  Drupal.plupload_widget = Drupal.plupload_widget || {};

  // Add Plupload events for autoupload and autosubmit.
  Drupal.plupload_widget.filesAddedCallback = function (up, files)
  {
    const $container = $(up.settings.container)

    // Remove old button if it still exists.
    $container
      .siblings('button.plupload-pause')
      .remove()

    // Insert pause/resume button
    const $pause = $('<button type="button" class="plupload-pause" disabled />')
    $pause.insertAfter($container)
    $pause.hide()

    // Update button based on current upload state.
    const update_button = function update_button ()
    {
      $pause.prop('disabled', false)
      const text = up.state === plupload.STARTED ? 'Pause' : 'Resume'
      $pause.text(text)
      $pause.show()
    }

    // Start or resume an upload
    const start_upload = function start_upload()
    {
      up.start()
      update_button()
    }

    // Pause the upload
    const pause_upload = function pause_upload()
    {
      up.stop()
      update_button()
    }

    // Timeout is necessary to allow for some unknown initialization operations
    setTimeout(start_upload, 100);

    // Setup pause/resume functionality
    $pause.on('click', function(){
      if ($(this).prop('disabled'))
        return

      if (up.state === plupload.STARTED)
        pause_upload()
      else
        start_upload()
    })
  };

  Drupal.plupload_widget.uploadCompleteCallback = function (up, files) {
    $(up.settings.container)
      .closest('form')
      .find('#edit-submit')
      .click()
  };

})(jQuery);
