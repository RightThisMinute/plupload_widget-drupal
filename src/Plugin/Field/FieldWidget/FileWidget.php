<?php

/**
 * @file
 * Contains \Drupal\plupload_widget\Plugin\Field\FieldWidget\FileWidget.
 */

namespace Drupal\plupload_widget\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Bytes;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Xss;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget as CoreFileWidget;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @FieldWidget(
 *   id = "plupload_file_widget",
 *   label = @Translation("PLupload widget"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class FileWidget extends CoreFileWidget {

  protected $id;

  public function getId() {
    if (empty($this->id)) {
      $this->id = uniqid();
    }
    return $this->id;
  }

  /**
   * Get the optimum chunk size.
   */
  public function getChunkSize() {
    // 500 Kb per chunk does not sound bad...
    $good_size = 1024 * 500;
    // This is what the PLUPLOAD module
    // field element takes as the default
    // chunk size.
    $size = Bytes::toInt(ini_get('post_max_size'));
    if ($size > $good_size)
      $size = $good_size;
    return $size;
  }

  /**
   * Returns the maximum configured
   * file size for the Field stroage
   * in Bytes.
   *
   * @return double|int
   */
  public function getMaxFileSize() {

    // We don't care about PHP's max post
    // or upload file size because we use
    // plupload.
    $size = $this->getFieldSetting('max_filesize');
    $size = Bytes::toInt($size);
    return $size;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['#replace_uploader'] = !$items[$delta]->getValue();
    return $element;
  }

  public static function process($element, FormStateInterface $form_state, $form) {

    $element = parent::process($element, $form_state, $form);

    // If the form element does not have
    // an uplad control, skip this.
    if (!isset($element['upload'])) {
      return $element;
    }

    $max_files = $element['#upload_max_files'];
    $location = $element['#upload_location'];
    $validators = $element['#upload_validators'];
    $id = $element['#upload_id'];
    $chunk_size = $form['#upload_chunk_size'];
    $max_upload = $form['#upload_max_size'];
    $cardinality = $form['#upload_cardinality'];

    // Change the element description because
    // the PLUPLOAD widget MUST have the
    // extension filters as descripiton.
    // @see \Drupal\plupload\Element\PlUploadFile::preRenderPlUploadFile()
    // @see \Drupal\file\Plugin\Field\FieldWidget\FileWidget::formElement()
    $file_upload_help = array(
       '#theme' => 'file_upload_help',
       '#description' => '',
       '#upload_validators' => '',
       '#cardinality' => $cardinality,
     );
    $element['#description'] = \Drupal::service('renderer')->renderPlain($file_upload_help);

    // Replace the upload HTML element with PLUPLOAD
    // for a single file.
    $element['upload'] = [
      '#type' => 'plupload',
      '#title' => t('Upload files'),
      //'#description' => t('This multi-upload widget uses Plupload library.'),
      '#autoupload' => TRUE,
      '#autosubmit' => TRUE,
      '#submit_element' => "[name={$element['upload_button']['#name']}]",
      '#upload_validators' => [
        'file_validate_extensions' => $validators['file_validate_extensions'],
      ],
      '#plupload_settings' => [
        'runtimes' => 'html5,flash,silverlight,html4',
        'chunk_size' => $chunk_size . 'b',
        'max_file_size' => $max_upload . 'b',
        'max_file_count' => $max_files,
      ],
      '#event_callbacks' => [
        'FilesAdded' => 'Drupal.plupload_widget.filesAddedCallback',
        'UploadComplete' => 'Drupal.plupload_widget.uploadCompleteCallback',
      ],
      '#attached' => [
        // We need to specify the plupload attachment because it is a default
        // and will be overriden by our value.
        'library' => ['plupload_widget/plupload_widget', 'plupload/plupload'],
      ]
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function submit($form, FormStateInterface $form_state) {
    return parent::submit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {

    $elements = parent::formMultipleElements($items, $form, $form_state);
    return $elements;

  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL) {

    $element = parent::form($items, $form, $form_state, $get_delta);

    $key = $items->getName() . 'widget_id';

    // Store the ID in the form_state
    if ($form_state->get($key)) {
      $this->id = $form_state->get($key);
    }
    else {
      $form_state->set($key, $this->getId());
    }

    $field_definition = $this->fieldDefinition->getFieldStorageDefinition();

    $cardinality = $field_definition->getCardinality();

    $form['#upload_max_files'] = $cardinality != FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED ? $cardinality - count($items) + 1 : -1;
    $form['#upload_location'] = $items[0]->getUploadLocation();
    $form['#upload_validators']  = $items[0]->getUploadValidators();
    $form['#upload_id']  = $this->getId();
    $form['#upload_chunk_size']  = $this->getChunkSize();
    $form['#upload_max_size']  = $this->getMaxFileSize();
    $form['#cardinality']  = $cardinality;

    // Add a process callback...
    $element['#after_build'] = array(array(self::class, 'formAfterBuild'));

    return $element;
  }

  public static function formAfterBuild($element, FormStateInterface $form_state) {
    return $element;
  }

  /**
   * Important!! The core FILE API relies on the value callback to save the managed file,
   * not the submit handler. The submit handler is only used for file deletions.
   */
  public static function value($element, $input = FALSE, FormStateInterface $form_state) {

    // We need to fake the element ID for the PlUploadFile form element
    // to work as expected as it is being nested in a form sub-element calle
    // upload.
    $id = $element['#id'];
    $id_backup = $id;

    // If a unique identifier added with '--', we need to exclude it
    if (preg_match('/(.*)(--[0-9A-Za-z-]+)$/', $id, $reg)) {
      $id = $reg[1];
    }

    // The form element is going to tell us if one
    // or more files where uploaded.
    $element['#id'] = $id . '-upload';
    $files = \Drupal\plupload\Element\PlUploadFile::valueCallback($element, $input, $form_state);
    $element['#id'] = $id_backup;
    if (empty($files)) {
      return parent::value($element, $input, $form_state);;
    }

    // During form rebuild after submit or ajax request this
    // method might be called twice, but we do not want to
    // generate the file entities twice....

    // This files are RAW files, they are not registered
    // anywhere, so won't get deleted on CRON runs :(
    $file = reset($files);

    $destination = \Drupal::config('system.file')->get('default_scheme') . '://' . $file['name'];
    $destination = file_stream_wrapper_uri_normalize($destination);

    /** @var \Drupal\file\Entity\File */
    $f = entity_create('file', array(
      'uri' => $file['tmppath'],
      'uid' => \Drupal::currentUser()->id(),
      'status' => 0,
      'filename' => drupal_basename($destination),
      'filemime' => \Drupal::service('file.mime_type.guesser')->guess($destination),
    ));

    $f->save();

    $return['fids'][] = $f->id();

    return $return;
  }


}
