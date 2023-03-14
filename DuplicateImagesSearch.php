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
      '!help' => t('Defines and restricts the search for duplicate images. The result will be a list of sets of duplicate images. Duplicate images are found by looking at their file name, file size and md5 hash. When you upload a duplicate image, Backdrop will append an underscore and a sequence number to the base name of the file. So the 1st check on file name is to check for this pattern.'),
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

    $is_private_path_defined = (bool) config_get('system.core', 'file_private_path');
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

    if (backdrop_substr($folder, -strlen('/')) !== '/') {
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

    // Group the list of files by possible duplicates.
    $duplicates = $this->groupDuplicatesByPattern($files);

    // And add them if their contents is indeed the same.
    foreach ($duplicates as $base_name => $file_group) {
      // A group of files can only contain duplicates if it contains at least 2
      // files.
      if (count($file_group) >= 2) {
        // Collect file size and md5 for each file in the set.
        $file_infos = array();
        $is_first = TRUE;
        foreach ($file_group as $file_name) {
          // Getting 2 md5's for large files when they differ on the 5th byte is
          // not efficient, but assuming that the the probability that these
          // files are indeed equal is close to 1, it might be more efficient to
          // let PHP check it internally and thus not to try to outperform
          // native PHP functions.
          $file_infos[$file_name] = array(
            'size' => filesize($folder . $file_name),
            'md5' => $use_md5 ? md5_file($folder . $file_name) : NULL,
            'status' => $is_first ? 'original' : NULL,
          );
          $is_first = FALSE;
        }

        // I have encountered a situation where I had 3 possible duplicates
        // (based on pattern): img.jp, img_0.jpg, and img_1.jpg. It turned out
        // that img_0.jpg and img_1.jpg were duplicates of each other while
        // img.jpg was another image.
        // So, compare all files with all the files after it: compare 1 with 2,
        // 1 with 3, and 1 with 4; then 2 with 3, and 2 with 4; then 3 with 4.
        do {
          // Compare the 1st file against the others.
          $file_name = array_shift($file_group);
          foreach ($file_group as $possible_duplicate) {
            if ($file_infos[$file_name]['status'] !== 'duplicate') {
              if ($file_infos[$file_name]['size'] === $file_infos[$possible_duplicate]['size']) {
                if ($file_infos[$file_name]['md5'] === $file_infos[$possible_duplicate]['md5']) {
                  // Confirmed duplicate: add to $result and we do no longer have
                  // to compare it against the other remaining files.
                  $this->duplicates[$folder . $possible_duplicate] = $folder . $file_name;
                  $file_infos[$possible_duplicate]['status'] = 'duplicate';

                  // Break on max duplicates to process if set so.
                  if (is_int($max_duplicates) && count($this->duplicates) >= $max_duplicates) {
                    throw new RuntimeException('Maximum number of images reached');
                  }
                }
                else {
                  if (!isset($file_infos[$possible_duplicate]['status'])) {
                    $file_infos[$possible_duplicate]['status'] = 'md5';
                  }
                }
              }
              else {
                if (!isset($file_infos[$possible_duplicate]['status'])) {
                  $file_infos[$possible_duplicate]['status'] = 'file size';
                }
              }
            }
          }
        } while (count($file_group) >= 2);

        // All files that did not match any other file (i.e. with status !=
        // duplicate or original) are treated as suspicious.
        reset($file_infos);
        $original = $folder . key($file_infos);
        foreach ($file_infos as $file_name => $file_info) {
          if ($file_info['status'] !== 'original' && $file_info['status'] !== 'duplicate') {
            $this->suspicious[$folder . $file_name] = array(
              'duplicate' => $folder . $file_name,
              'original' => $original,
              'reason' => $file_info['status'],
            );
          }
        }

        // Try to prevent time-outs by restarting the timer.
        @set_time_limit(ini_get('max_execution_time'));
      }
    }

    // Recursively call search() on each sub folder. Excluded sub_folders only
    // holds on the top level, so pass an empty array for that.
    foreach ($dirs as $dir) {
      $this->search($dir, array(), $use_md5, $max_duplicates);
    }
  }

  /**
   * Retrieves possible duplicates, based on pattern, from a set of file names.
   *
   * On upload, Backdrop will attach an underscore and a sequence number to the
   * basename of files whose name already exist. Thus image.jpg becomes
   * image_0.jpg on 2nd upload, image_1.jpg on 3rd upload, etc.
   *
   * Cases we have to consider:
   * - image.jpg and image_0.jpg exists: consider image_0.jpg as possible
   *   duplicate of image.jpg
   * - image_0.jpg and image_1.jpg exists, but image.jpg does not exist:
   *   possibly image.jpg has been uploaded 3 times, but the first upload has
   *   already been removed. Consider image_1.jpg as possible duplicate of
   *   image_0.jpg (issue [#3058162]).
   *
   * To tackle the 2nd case, we keep track of a list of "base names": everything
   * before the last _ in the file name plus its extension (we don't want to
   * match img.jpg and img_1.png). However as many digital cameras use a naming
   * scheme like img_<nnnn>.jpg or img_<yymmdd_hhmmss>.jpg, we will restrict the
   * part after the last _ to at most 2 digits (100 duplicate uploads).
   *
   * @param string[] $files
   *   List of file names without folder part.
   *
   * @return string[][]
   *   List of sets of possible duplicates keyed by the base name (file name
   *   without the finishing _n[n] but with its extension).
   */
  protected function groupDuplicatesByPattern(array $files) {
    $result = array();

    foreach ($files as $file) {
      // Check if the basename has the format to possibly be a duplicate.
      $parts = pathinfo($file);
      if (!empty($parts['filename']) && !empty($parts['extension'])) {
        // Get base name.
        $base_name = $parts['basename'];
        $matches = array();
        $match = preg_match('/^(.+)(_(\d\d?))$/', $parts['filename'], $matches);
        if ($match) {
          $base_name = $matches[1] . '.' . $parts['extension'];
        }

        // Has this base name already been encountered before?
        if (!array_key_exists($base_name, $result)) {
          // No: a new base name.
          $result[$base_name] = array($file);
        }
        else {
          // Yes: this might be a duplicate.
          $result[$base_name][] = $file;
        }
      }
    }
    return $result;
  }

}
