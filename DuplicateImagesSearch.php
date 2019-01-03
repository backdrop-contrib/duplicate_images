<?php

/**
 * @file
 * Class DuplicateImagesSearch.
 */

/**
 * Class DuplicateImagesSearch.
 *
 * Contains the form definition and processing for the search duplicates step.
 */
class DuplicateImagesSearch extends DuplicateImagesBaseForm {

  /**
   * List of duplicates found.
   *
   * @var string[]
   */
  protected $duplicates = array();

  /**
   * List of suspicious images found (names look equal but size/md5 differs).
   *
   * @var string[]
   */
  protected $suspicious = array();

  /**
   * {@inheritdoc}
   */
  protected function getButton() {
    return t('Search duplicates');
  }

  /**
   * {@inheritdoc}
   */
  protected function getHelp() {
    return t('!step: !help', array(
      '!step' => t('Search duplicates'),
      '!help' => t('Defines and restricts the search for duplicate images. The result will be a list of sets of duplicate images. Duplicate images are found by looking at their file name, file size and md5 hash. When you upload a duplicate image, Drupal will append an underscore and a sequence number to the base name of the file. So the 1st check on file name is to check for this pattern.'),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function fields(array $form, array &$form_state) {
    $form = parent::fields($form, $form_state);

    $form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Duplicate search options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => FALSE,
    );

    $is_private_path_defined = (bool) variable_get('file_private_path', FALSE);
    $options = array(
      'public://' => 'public://',
    );
    if ($is_private_path_defined) {
      $options['private://'] = 'private://';
    }
    $form['options']['file_systems'] = array(
      '#type' => 'checkboxes',
      '#title' => t('File systems to search'),
      '#options' => $options,
      '#default_value' => array('public://'),
      '#description' => t('Indicate which file systems to search for duplicates.'),
      '#access' => $is_private_path_defined,
    );

    $form['options']['excluded_sub_folders'] = array(
      '#type' => 'textfield',
      '#title' => t('Sub folders to exclude'),
      '#default_value' => 'css, ctools, js, languages, styles',
      '#description' => t('Indicate which sub folders in the selected file systems to exclude, use a comma separated list.'),
      '#size' => 120,
    );
    $form['options']['use_md5'] = array(
      '#type' => 'checkbox',
      '#title' => t('Use md5 to determine equality'),
      '#default_value' => 1,
      '#description' => t('Indicate whether file duplicates will also be tested for equality by using <a href="http://php.net/manual/en/function.md5-file.php">md5_file()</a> as well. This will prevent false positives but might slow down the process when there are many big files to check.'),
    );
    $form['options']['max_duplicates'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum number of duplicate images to process'),
      '#default_value' => '',
      '#description' => t('If your system does not allow to reset the time limit (max_execution_time) or the maximum number of fields on a post (max_input_vars), or you just hate long page waits, you can limit the number of duplicate images that will be searched for and processed. Leave empty to process all duplicates at once.'),
      '#size' => 4,
    );

    $image_styles = array();
    foreach (image_styles() as $image_style) {
      $image_styles[$image_style['name']] = $image_style['label'];
    }
    $thumbnail_styles = array('' => t('Do not show thumbnails')) + $image_styles;
    $thumbnail_style = array_key_exists('thumbnail', $image_styles) ? 'thumbnail' : '';

    $large_styles = array(
      '' => t('Do not make a link of the thumbnail'),
      'full image' => t('Link to full image'),
    ) + $image_styles;
    $large_style = array_key_exists('large', $image_styles) ? 'large' : 'full image';

    $form['options']['thumbnail_style'] = array(
      '#type' => 'select',
      '#title' => t('Image style to show suspicious images'),
      '#options' => $thumbnail_styles,
      '#default_value' => $thumbnail_style,
      '#description' => t('Suspicious files are files that may be a duplicate but either the file size or md5 hash differs. These will be listed separately and a thumbnail or link to the document will be shown.'),
    );
    $form['options']['large_style'] = array(
      '#type' => 'select',
      '#title' => t('Image style to link the thumbnail to'),
      '#options' => $large_styles,
      '#default_value' => $large_style,
      '#description' => t('Defines the image style the thumbnail will link to. Documents will always be linked to the document itself.'),
    );
    if (module_exists('colorbox') && user_access('administer site configuration')) {
      $form['options']['colorbox_info'] = array(
        '#type' => 'markup',
        '#markup' => t('Note: you may want to temporarily remove "admin*" from the Colorbox advanced setting "%setting" at !path.',
          array(
            '%setting' => t('Show Colorbox on specific pages'),
            '!path' => l(t('Colorbox settings'), 'admin/config/media/colorbox'),
          )),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    parent::submit($form, $form_state);

    $file_systems = array_filter($form_state['values']['file_systems']);
    $excluded_sub_folders = explode(',',
      $form_state['values']['excluded_sub_folders']);
    array_walk(
      $excluded_sub_folders,
      function (&$item) {
        $item = trim($item);
      });
    $use_md5 = $form_state['values']['use_md5'] == 1;
    $max_duplicates = !empty($form_state['values']['max_duplicates']) ? (int) $form_state['values']['max_duplicates'] : NULL;

    $results = $this->exec($file_systems, $excluded_sub_folders, $use_md5, $max_duplicates);
    $form_state['duplicate_images'] = $results[0];
    $form_state['suspicious_images'] = $results[1];
    $form_state['thumbnail_style'] = $form_state['values']['thumbnail_style'];
    $form_state['large_style'] = $form_state['values']['large_style'];
  }

  /**
   * Executes the find duplicates step.
   *
   * @param string[] $file_systems
   *   Folder to search.
   * @param string[] $excluded_sub_folders
   *   List of sub folders to exclude.
   * @param bool $use_md5
   *   Also use md5 to determine equality.
   * @param int|null $max_duplicates
   *   Max. number of duplicate images to return.
   *
   * @return array[]
   *   2 arrays:
   *   - array with $duplicate => $original string pairs
   *   - array with $suspicious => array with keys duplicate, original, reason.
   */
  public function exec(array $file_systems, array $excluded_sub_folders, $use_md5, $max_duplicates) {
    foreach ($file_systems as $file_system) {
      try {
        $this->search($file_system, $excluded_sub_folders, $use_md5, $max_duplicates);
      }
      catch (RuntimeException $e) {
        if ($e->getMessage() !== 'Maximum number of images reached') {
          throw $e;
        }
      }
    }
    return array($this->duplicates, $this->suspicious);
  }

  /**
   * Recursively searches for duplicate images.
   *
   * @param string $folder
   *   Folder to search.
   * @param string[] $excluded_sub_folders
   *   List of sub folders to exclude.
   * @param bool $use_md5
   *   Also use md5 to determine equality.
   * @param int|null $max_duplicates
   *   Max. number of duplicate images to return.
   */
  protected function search($folder, array $excluded_sub_folders, $use_md5, $max_duplicates) {
    // Create a list of files to compare and a list of sub folders to
    // recursively search.
    $files = array();
    $dirs = array();
    $list = scandir($folder);

    if (drupal_substr($folder, -strlen('/')) !== '/') {
      $folder .= '/';
    }
    foreach ($list as $file) {
      if ($file === '.' || $file === '..') {
        // Skip.
      }
      else {
        if (is_dir($folder . $file)) {
          // Folder: skip if it is an excluded sub folder.
          if (!in_array($file, $excluded_sub_folders)) {
            $dirs[] = $folder . $file;
          }
        }
        else {
          // File: process.
          $files[] = $file;
        }
      }
    }

    // Compare the list of files to spot duplicates.
    $duplicates = $this->getDuplicatesByPattern($files);

    // And add them if their contents is the same.
    foreach ($duplicates as $duplicate => $original) {
      $duplicate = $folder . $duplicate;
      $original = $folder . $original;
      // Getting 2 md5's for large files when they differ on the 5th byte is
      // not efficient, but assuming that the the probability that these files
      // are indeed equal is close to 1, it might be more efficient to let PHP
      // check it internally and thus not to try to outperform native PHP
      // functions.
      if (filesize($duplicate) === filesize($original)) {
        if (!$use_md5 || md5_file($duplicate) === md5_file($original)) {
          $reason = NULL;
        }
        else {
          $reason = 'md5';
        }
      }
      else {
        $reason = 'filesize';
      }

      if ($reason === NULL) {
        // Confirmed duplicate: add to $result.
        $this->duplicates[$duplicate] = $original;
        if (is_int($max_duplicates) && count($this->duplicates) >= $max_duplicates) {
          throw new RuntimeException('Maximum number of images reached');
        }
      }
      else {
        // Suspicious but not a real duplicate: add to $suspicious.
        $this->suspicious[$duplicate] = array(
          'duplicate' => $duplicate,
          'original' => $original,
          'reason' => $reason,
        );
      }

      // Try to prevent time-outs by restarting the timer.
      @set_time_limit(ini_get('max_execution_time'));
    }

    // Recursively call search() on each sub folder. Excluded sub_folders only
    // holds on the top level, so pass an empty array for that.
    foreach ($dirs as $dir) {
      $this->search($dir, array(), $use_md5, $max_duplicates);
    }
  }

  /**
   * Retrieves (possible) duplicates from a list of files.
   *
   * On upload, Drupal will attach an underscore and a sequence number to the
   * basename of files whose name already exist. Thus image.ext becomes
   * image_0.ext on 2nd upload, image_1.ext on 3rd upload, etc.
   *
   * @param string[] $files
   *   List of file names without folder part.
   *
   * @return string[]
   *   List of possible duplicates (keys) and the file they might be a duplicate
   *   of (values).
   */
  protected function getDuplicatesByPattern(array $files) {
    $result = array();

    foreach ($files as $file) {
      // Check if the basename has the format to possibly be a duplicate.
      $parts = pathinfo($file);
      if (!empty($parts['filename'])) {
        $matches = array();
        if (preg_match('/^(.+)_(\d+)$/', $parts['filename'], $matches) === 1) {
          // It might be a duplicate: construct the filename it would be a
          // duplicate of: basename without underscore and sequence number plus
          // same extension.
          $duplicate_of = $matches[1];
          if (isset($parts['extension'])) {
            $duplicate_of .= '.' . $parts['extension'];
          }
          // And if that indeed exists, consider it as duplicate for now (more
          // checks like file size or file contents may follow).
          if (in_array($duplicate_of, $files)) {
            $result[$file] = $duplicate_of;
          }
        }
      }
    }
    return $result;
  }

}
