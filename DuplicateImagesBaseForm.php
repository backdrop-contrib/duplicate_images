<?php

/**
 * @file
 * Class DuplicateImagesBaseForm.
 */

/**
 * Class DuplicateImagesBaseForm defines:
 * - Some base methods for the other form classes.
 * - Some static methods used by the form wizard code in the .module file.
 */
abstract class DuplicateImagesBaseForm {
  /**
   * @var string
   */
  protected $step = '';

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
   */
  abstract protected function getButton();

  /**
   * Returns the help text for the current step of the process.
   *
   * @return string
   */
  abstract protected function getHelp();

  /**
   * Get the step specific form fields.
   *
   * @param array[] $form
   * @param array $form_state
   *
   * @return array[]
   */
  public function fields(array $form, array &$form_state) {
    $this->step = isset($_GET['op']) ? $_GET['op'] : 'intro';

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
   * Submit handler for this step.
   *
   * @param array[] $form
   * @param array $form_state
   */
  public function submit(/** @noinspection PhpUnusedParameterInspection */ array $form, array &$form_state) {
    $form_state['rebuild'] = TRUE;
  }

  /**
   * Returns a translated list of tasks.
   *
   * @return string[]
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
   * Returns the next step.
   *
   * @param string $step
   *
   * @return string
   *   The next step, or the empty string if this is the last step.
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
   *
   * @return string
   *   The next step, or the empty string if this is the last step.
   */
  static public function getNext($step) {
    $steps = array_keys(static::getSteps());
    $index = array_search($step, $steps);
    return $index !== FALSE && $index < count($steps) - 1 ? $steps[++$index] : '';
  }

  /**
   * Returns the progress text for the current step of the process.
   *
   * @return string
   *
   * @throws \Exception
   */
  protected function getProgress() {
    /** @noinspection PhpIncludeInspection */
    require_once DRUPAL_ROOT . '/includes/theme.maintenance.inc';
    return theme('task_list', array('items' => static::getSteps(), 'active' => $this->step));
  }

}
