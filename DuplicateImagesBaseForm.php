<?php

/**
 * @file
 * Class DuplicateImagesBaseForm.
 */

/**
 * Class DuplicateImagesBaseForm.
 *
 * Defines:
 * - Some base methods for the other form classes.
 * - Some static methods used by the form wizard code in the .module file.
 */
abstract class DuplicateImagesBaseForm {
  /**
   * Step.
   *
   * @var string
   */
  protected $step;

  /**
   * DuplicateImagesBaseForm constructor.
   */
  public function __construct() {
    $class = get_class($this);
    $this->step = strtolower(substr($class, strpos($class, 'DuplicateImages') + strlen('DuplicateImages')));
  }

  /**
   * Returns the button text for the current step of the process.
   *
   * @return string
   *   Button text for the current step of the process.
   */
  abstract protected function getButton();

  /**
   * Returns the help text for the current step of the process.
   *
   * @return string
   *   Help text for the current step of the process.
   */
  abstract protected function getHelp();

  /**
   * Get the step specific form fields.
   *
   * @param array[] $form
   *   Form.
   * @param array $form_state
   *   Form state.
   *
   * @return array[]
   *   Step specific form fields.
   */
  public function fields(array $form, array &$form_state) {
    $this->step = static::getStep();

    // Common settings and elements: task list +  help.
    $form_state['cache'] = TRUE;
    $form['#action'] = url(current_path(), array('query' => array('op' => $this->getNext($this->step))));

    $form['progress'] = array(
      '#type' => 'markup',
      '#markup' => $this->getProgress(),
    );

    $form['step'] = array(
      '#type' => 'markup',
      '#markup' => $this->getHelp(),
    );

    // Buttons.
    if ($this->step !== 'finished') {
      $form['actions'] = array(
        '#type' => 'actions',
        'submit' => array(
          '#type' => 'submit',
          '#value' => $this->getButton(),
        ),
        '#weight' => 999,
      );
    }

    return $form;
  }

  /**
   * Returns the progress text for the current step of the process.
   *
   * @return string
   *   Progress text for the current step of the process.
   *
   * @throws \Exception
   */
  protected function getProgress() {
    /** @noinspection PhpIncludeInspection */
    require_once DRUPAL_ROOT . '/includes/theme.maintenance.inc';
    return theme('task_list', array('items' => static::getSteps(), 'active' => $this->step));
  }

  /**
   * Submit handler for this step.
   *
   * @param array[] $form
   *   Form.
   * @param array $form_state
   *   Form state.
   */
  public function submit(/** @noinspection PhpUnusedParameterInspection */ array $form, array &$form_state) {
    $form_state['rebuild'] = TRUE;
  }

  /**
   * Returns the html for showing a possibly clickable thumbnail.
   *
   * @param string $file_name
   *   File to get thumbnail for.
   * @param string $thumbnail_style
   *   Image style to use for the thumbnail.
   * @param string $large_style
   *   Image style to link the thumbnail to.
   * @param int $i
   *   Postfix to use in colorbox gallery id.
   *
   * @return string
   *   Html for showing a possibly clickable thumbnail.
   *
   * @throws \Exception
   */
  protected function getThumbnailHtml($file_name, $thumbnail_style, $large_style, $i) {
    $info = image_get_info($file_name);
    if (!empty($info['extension'])) {
      $result = theme('image_style',
        array('style_name' => $thumbnail_style, 'path' => $file_name) + $info);
    }
    else {
      $file = new stdClass();
      $file->mimetype = file_get_mimetype($file_name);
      $result = theme('file_icon', array('file' => $file, 'alt' => ''));
    }

    if (!empty($large_style)) {
      if ($large_style === 'full image' || empty($info['extension'])) {
        $link_path = file_create_url($file_name);
      }
      else {
        $link_path = image_style_url($large_style, $file_name);
      }
      $result = l($result, $link_path, array(
        'html' => TRUE,
        'attributes' => array(
          'class' => array('colorbox'),
          'rel' => "gallery-all-$i",
        ),
      ));
    }
    return $result;
  }

  /**
   * Returns the current step based on the request query.
   *
   * @return string
   *   Current step based on the request query.
   */
  static public function getStep() {
    return isset($_GET['op']) ? $_GET['op'] : 'intro';
  }

  /**
   * Returns a translated list of tasks.
   *
   * @return string[]
   *   Translated list of tasks
   */
  public static function getSteps() {
    $steps = array(
      'intro' => t('Introduction'),
      'search' => t('Search duplicates'),
      'usages' => t('Find usages'),
      'update' => t('Update usages'),
      'delete' => t('Delete duplicates'),
      'finished' => t('Finished'),
    );

    return $steps;
  }

  /**
   * Returns the previous step.
   *
   * @param string $step
   *   Step.
   *
   * @return string
   *   The previous step, or the empty string if this is the first step.
   */
  static public function getPrev($step) {
    $steps = array_keys(static::getSteps());
    $index = array_search($step, $steps);
    return $index ? $steps[--$index] : '';
  }

  /**
   * Returns the next step.
   *
   * @param string $step
   *   Step.
   *
   * @return string
   *   The next step, or the empty string if this is the last step.
   */
  static public function getNext($step) {
    $steps = array_keys(static::getSteps());
    $index = array_search($step, $steps);
    return $index !== FALSE && $index < count($steps) - 1 ? $steps[++$index] : '';
  }

}
