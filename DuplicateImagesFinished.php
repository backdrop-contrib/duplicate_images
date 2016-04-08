<?php

/**
 * Class DuplicateImagesFinished
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
    return t('The process has finished. You can now disable this module. BTW, this may be a good time to install the !link module which allows you to reuse already uploaded images.',
      array('!link' => l('FileField Sources', 'https://www.drupal.org/project/filefield_sources', array('external' => TRUE))));
  }

  /**
   * {@inheritdoc}
   */
  public function fields(array $form, array &$form_state) {
    $form = parent::fields($form, $form_state);

    $delete_results = $form_state['delete_results'];
    $success = array();
    $failures = array();
    foreach ($delete_results as $file_name => $delete_result) {
      if ($delete_result === TRUE) {
        $success[] = $file_name;
      }
      else if ($delete_result === FALSE) {
        $failures[] = t('%file_name: failed to delete.', array('%file_name' => $file_name));
      }
      else { // $delete_result = fid of managed file delete failure (int).
        $failures[] = t('%file_name: failed to delete managed file %fid.', array('%file_name' => $file_name, '%fid' => $delete_result));
      }
    }

    if (!empty($success)) {
      $success = '<ul><li>' . implode('</li><li>', $success) . '</li></ul>';
      $form['success'] = array(
        '#type' => 'markup',
        '#markup' => '<p>' . t('The following files were deleted successfully:') . '</p>' . $success,
      );
    }
    if (!empty($failures)) {
      $failures = '<ul><li>' . implode('</li><li>', $failures) . '</li></ul>';
      $form['failures'] = array(
        '#type' => 'markup',
        '#markup' => '<p>' . t('The following files could not be deleted:') . '</p>' . $failures,
      );
    }

    if (module_exists('colorbox')) {
      $form['options']['colorbox_info'] = array(
        '#type' => 'markup',
        '#markup' => t('Note: you may want to restore the "admin*" line to the Colorbox advanced setting "%setting" at !path.',
          array(
            '%setting' => t('Show Colorbox on specific pages'),
            '!path' => l('admin/config/media/colorbox', 'admin/config/media/colorbox'),
          )),
      );
    }

    return $form;
  }

}
