<?php

/**
 * Contains the form definition for the first (purely informational) step.
 */
class DuplicateImagesIntro extends DuplicateImagesBaseForm {

  /**
   * {@inheritdoc}
   */
  protected function getButton() {
    return t('Start');
  }

  /**
   * {@inheritdoc}
   */
  protected function getHelp() {
    return t('<h2>This module deduplicates images and other files on the public or private file system.</h2>
    <p>Whenever a user uploads an image or document, a new file is created on the file system.
    Even if the file (name) already exists, because in these cases, Drupal does not ask what to do, but just adds an underscore and a number to the filename to make it unique.
    This module is able to detect and correct these duplicate files.</p>
    <p>Step by step it: searches for duplicate images; searches for their usages; updates the usages to refer to the main image; and then deletes the no longer used and referred to duplicates.
    Each step will present its results to allow you to check and double check that the correct actions are taking place.
    Most steps allow you to influence what will be done next. This allows to test with a few images first.</p>
    <p>Those steps that may take quite some time will reset the time limit using <a href="http://cl1.php.net/manual/en/function.set-time-limit.php">set_time_limit()</a>.
    However, this function may not work on your system, in which case you can limit the amount of images processed.</p>
    <p>WARNING: This module is not able to detect references to images or managed files in custom or contrib non-field tables!</p>');
  }

  /**
   * {@inheritdoc}
   */
  public function fields(array $form, array &$form_state) {
    $form = parent::fields($form, $form_state);
    return $form;
  }

}
