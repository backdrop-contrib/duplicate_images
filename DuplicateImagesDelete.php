<?php

/**
 * @file
 * Class DuplicateImagesDelete.
 */

/**
 * Class DuplicateImagesDelete.
 *
 * Contains the form definition and processing for the delete duplicate images
 * step.
 */
class DuplicateImagesDelete extends DuplicateImagesBaseForm {

  /**
   * {@inheritdoc}
   */
  protected function getButton() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  protected function getHelp() {
    return t('!step: !help', array(
      '!step' => t('Delete duplicates'),
      '!help' => t('Now that the duplicates are no longer referred to, this step can safely delete them, both as managed file entity and as file on the file system.'),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function fields(array $form, array &$form_state) {
    $form = parent::fields($form, $form_state);

    $form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Delete options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => FALSE,
    );

    // Create a list of possible file deletes.
    $file_deletes = array_keys($form_state['selected_duplicate_images']) + array_keys($form_state['selected_suspicious_images']);
    $file_deletes = array_combine($file_deletes, $file_deletes);

    // Filter out when entities referring to it where not updated.
    foreach ($form_state['duplicate_references'] as $entity_type => $entities) {
      foreach ($entities as $entity_id => $duplicates) {
        // An entity was not updated if it was not selected or the update
        // failed.
        $selected = array_key_exists("$entity_type $entity_id", $form_state['selected_entities_to_update']);
        $success = isset($form_state['updates_performed']["$entity_type $entity_id"]) && $form_state['updates_performed']["$entity_type $entity_id"];
        if (!$selected || !$success) {
          // Remove the duplicates this entity was referring to.
          $file_deletes = array_diff_key($file_deletes, $duplicates);
        }
      }
    }

    // $managed_file_deletes is a list of fid => duplicate pairs.
    $managed_file_deletes = $form_state['duplicate_managed_files'];
    // We only delete the managed file if we may delete the image as well.
    $managed_file_deletes = array_intersect($managed_file_deletes, $file_deletes);
    // If we remove a managed file, the file will also be deleted by
    // file_delete(), so we do not have to do that ourselves anymore.
    $file_deletes = array_diff($file_deletes, $managed_file_deletes);

    $form['options']['managed_file_deletes'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Managed files (@count)', array('@count' => count($managed_file_deletes))),
      '#options' => $managed_file_deletes,
      '#default_value' => array_keys($managed_file_deletes),
      '#description' => t('These are the managed files that can be deleted.'),
    );
    $form['options']['file_deletes'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Files (@count)', array('@count' => count($file_deletes))),
      '#options' => $file_deletes,
      '#default_value' => array_keys($file_deletes),
      '#description' => t('These are the non managed files that can be deleted.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    parent::submit($form, $form_state);

    $form_state['selected_managed_file_deletes'] = array_filter($form_state['values']['managed_file_deletes']);
    $form_state['selected_file_deletes'] = array_filter($form_state['values']['file_deletes']);

    $form_state['delete_results'] = $this->exec($form_state['selected_managed_file_deletes'], $form_state['selected_file_deletes']);
  }

  /**
   * Deletes the selected managed files and files.
   *
   * @param array $selected_managed_file_deletes
   * @param array $selected_file_deletes
   *
   * @return array
   *   array[file_name => bool|fid] (success)
   */
  public function exec(array $selected_managed_file_deletes, array $selected_file_deletes) {
    $results = array_merge(
      $this->deleteManagedFiles($selected_managed_file_deletes),
      $this->deleteFiles($selected_file_deletes)
    );
    return $results;
  }

  /**
   * Deletes a list of managed files.
   *
   * @param string[] $selected_managed_file_deletes
   *   List of files to delete keyed by fid.
   *
   * @return array
   *   array[file_name => fid|true] list of results.
   */
  protected function deleteManagedFiles(array $selected_managed_file_deletes) {
    $results = array();
    $managed_files = file_load_multiple(array_keys($selected_managed_file_deletes));
    foreach ($managed_files as $managed_file) {
      // Imce may have set a "usage" that may prevent us from deleting it: force
      // the delete.
      if (file_delete($managed_file, TRUE) === TRUE) {
        $results[$managed_file->uri] = TRUE;
        $this->deleteDerivatives($managed_file->uri);
      }
      else {
        $results[$managed_file->uri] = $managed_file->fid;
      }
    }
    return $results;
  }

  /**
   * Deletes a set of files and their possible image style derivatives.
   *
   * @param string[] $file_deletes
   *
   * @return bool[]
   *   array[file_name => bool] (success)
   */
  protected function deleteFiles(array $file_deletes) {
    $results = array();
    foreach ($file_deletes as $file_name) {
      $results[$file_name] = $this->deleteFile($file_name);
    }
    return $results;
  }

  /**
   * Deletes a file and its possible image style derivatives.
   *
   * @param string $file_name
   *
   * @return bool
   *   true on success, false on failure.
   */
  protected function deleteFile($file_name) {
    $this->deleteDerivatives($file_name);
    return drupal_unlink($file_name);
  }

  /**
   * Deletes the image derivatives of a file.
   *
   * @param string $file_name
   */
  protected function deleteDerivatives($file_name) {
    $image_styles = image_styles();
    foreach ($image_styles as $image_style) {
      $derivative = image_style_path($image_style['name'], $file_name);
      if (is_file($derivative)) {
        drupal_unlink($derivative);
      }
    }
  }

}
