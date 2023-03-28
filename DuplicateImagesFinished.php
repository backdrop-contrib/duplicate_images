<?php

/**
 * @file
 * Class DuplicateImagesFinished.
 */

/**
 * Class DuplicateImagesFinished.
 *
 * Contains the form definition for the last (purely informational) step.
 */
class DuplicateImagesFinished extends DuplicateImagesBaseForm {

  /**
   * {@inheritdoc}
   */
  protected function getButton() {
    return t('Finished');
  }

  /**
   * {@inheritdoc}
   */
  protected function getHelp() {
    return t('The process has completed. You can !runitagain, or if you are finished you can disable this module. You can prevent duplicates going forward by using the built-in image library or installing !link to reuse existing files and images.',
      array(
        '!link' => l(t('FileField Sources'), 'https://www.backdropcms.org/project/filefield_sources', array('external' => TRUE)),
        '!runitagain' => l(t('run it again'), 'admin/config/media/duplicate-images'),
      ));
  }

  /**
   * {@inheritdoc}
   */
  public function fields(array $form, array &$form_state) {
    $form = parent::fields($form, $form_state);
    $delete_results = $_SESSION['duplicate_images']['delete_results'];
    $success = array();
    $failures = array();
    foreach ($delete_results as $file_name => $delete_result) {
      if ($delete_result === TRUE) {
        $success[] = $file_name;
      }
      elseif ($delete_result === FALSE) {
        $failures[] = t('%file_name: failed to delete.', array('%file_name' => $file_name));
      }
      else {
        // $delete_result = fid of managed file delete failure (int).
        $failures[] = t('%file_name: failed to delete managed file %fid.', array('%file_name' => $file_name, '%fid' => $delete_result));
      }
    }

    if (!empty($success)) {
      $success = '<ul><li>' . implode('</li><li>', $success) . '</li></ul>';
      $form['success'] = array(
        '#type' => 'markup',
        '#markup' => t('<p>The following files were deleted successfully:</p>') . $success,
      );
    }
    if (!empty($failures)) {
      $failures = '<ul><li>' . implode('</li><li>', $failures) . '</li></ul>';
      $form['failures'] = array(
        '#type' => 'markup',
        '#markup' => t('<p>The following files could not be deleted:</p>') . $failures,
      );
    }

    if (module_exists('colorbox') && user_access('administer site configuration')) {
      $form['options']['colorbox_info'] = array(
        '#type' => 'markup',
        '#markup' => t('Note: you may want to restore the "admin*" line to the Colorbox advanced setting "%setting" at !path.',
          array(
            '%setting' => t('Show Colorbox on specific pages'),
            '!path' => l(t('Colorbox settings'), 'admin/config/media/colorbox'),
          )),
      );
    }
    unset($_SESSION['duplicate_images']);
    return $form;
  }

}
