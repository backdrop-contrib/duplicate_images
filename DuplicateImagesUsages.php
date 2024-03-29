<?php

/**
 * @file
 * Class DuplicateImagesUsages.
 */

/**
 * Class DuplicateImagesUsages.
 *
 * Contains the form definition and processing for the find usages step.
 */
class DuplicateImagesUsages extends DuplicateImagesBaseForm {

  /**
   * The list of usages found.
   *
   * The format is such that it allows us to update these usages to refer to the
   * original image.
   *
   * @var array[]
   */
  protected $updateInstructions = array();

  /**
   * The list of references to duplicates keyed by entity type and id.
   *
   * This allows us to not delete duplicates if not all referring entities were
   * correctly updated.
   *
   * @var array[]
   */
  protected $duplicateReferences = array();

  /**
   * The list of managed files that refer to a duplicate.
   *
   * These may be deleted if all usages are correctly updated.
   * Key: fid, value: duplicate.
   *
   * @var string[]
   */
  protected $duplicateManagedFiles = array();

  /**
   * List of fids of the original images keyed by file name.
   *
   * @var array
   */
  protected $originalIds = array();

  /**
   * Array of field name => "referring column in that field" key-value pairs.
   *
   * @var null|array[]
   */
  protected $fieldsReferringManagedFiles = NULL;

  /**
   * Contains information about the local stream wrappers public and private.
   *
   * @var array[]
   */
  protected $streamInfo;

  /**
   * {@inheritdoc}
   */
  protected function getButton() {
    return t('Find');
  }

  /**
   * {@inheritdoc}
   */
  protected function getHelp() {
    return t('!step: !help', array(
      '!step' => t('Find usages'),
      '!help' => t('Defines where to look for usages of the duplicate images found. The result will be a list of usages of the duplicates.'),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function fields(array $form, array &$form_state) {
    $form = parent::fields($form, $form_state);

    $form['results'] = array(
      '#type' => 'fieldset',
      '#title' => t('Results'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => FALSE,
    );

    $options = array(
      'none' => 'None',
    );
    $duplicate_images = (!empty($_SESSION['duplicate_images']['duplicate_images'])) ? $_SESSION['duplicate_images']['duplicate_images'] : array();
    foreach ($duplicate_images as $duplicate => &$original) {
      $_SESSION['duplicate_images']['duplicate_images_mapping'][$duplicate] = $original;
      $original = "$duplicate: <em>$original</em>";
    }
    $form['results']['duplicate_images'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Duplicates found (@count)', array('@count' => count($duplicate_images))),
      '#options' => array_merge($options, $duplicate_images),
      '#default_value' => array_keys($duplicate_images),
      '#description' => t('These are the duplicates found.'),
    );

    $thumbnail_style = !empty($_SESSION['duplicate_images']['thumbnail_style']) ? $_SESSION['duplicate_images']['thumbnail_style'] : 'thumbnail';
    $large_style = $_SESSION['duplicate_images']['large_style'];
    $suspicious_images = (!empty($_SESSION['duplicate_images']['suspicious_images'])) ? $_SESSION['duplicate_images']['suspicious_images'] : array();
    $options = array(
      'none' => 'None',
    );
    $i = 1;
    foreach ($suspicious_images as &$suspicious_info) {
      $thumbs = '';
      if (!empty($thumbnail_style)) {
        $thumbs = $this->getThumbnailHtml($suspicious_info['duplicate'], $thumbnail_style, $large_style, $i)
          . ' ' . $this->getThumbnailHtml($suspicious_info['original'], $thumbnail_style, $large_style, $i)
          . ' ';
      }
      $suspicious_info = t('!thumbs%suspicious: %suspicious_of, but %reason differs', array(
        '!thumbs' => $thumbs,
        '%suspicious' => $suspicious_info['duplicate'],
        '%suspicious_of' => $suspicious_info['original'],
        '%reason' => $suspicious_info['reason'],
      ));
      $i++;
    }
    $form['results']['suspicious_images'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Suspicious images found (@count)', array('@count' => count($suspicious_images))),
      '#options' => array_merge($options, $suspicious_images),
      '#default_value' => array(),
      '#description' => t('These are images that have the pattern to be a duplicate of another image but do differ for the indicated reason.'),
    );

    $form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Usage search options'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#tree' => FALSE,
    );

    $options = array(
      'managed_files' => t('Look in managed files for usages'),
    );
    $form['options']['managed_files'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Managed files'),
      '#options' => $options,
      '#default_value' => array_keys($options),
      '#description' => t('Indicate whether to search the managed files (and thus also references to managed files) for usages. You should do so, but if you are sure that all (duplicate) images were uploaded by other means than the Drupal UI, and thus no image or file field is referring to them, unchecking this option may considerably speed up this phase.'),
    );

    $options = $this->getFieldsThatMayBeSearched();
    $form['options']['fields_media_tag'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Fields to look for media tag usages'),
      '#options' => $options,
      '#default_value' => module_exists('media_wysiwyg') ? array_keys($options) : array(),
      '#description' => t('Define where to look for media tags referring to managed files. Media tags can be inserted with the !link (sub)module. In principle, when you use this module, all (long) text field_names should be searched for, but if, e.g, you are sure that none of the duplicate images are tagged in some text field(s), you may speed up this phase by deselecting that field.',
        array('!link' => l(t('Media Wysiwyg'), 'https://www.drupal.org/project/media', array('external' => TRUE)))),
    );

    $form['options']['fields_uri'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Fields to look for image URI usages'),
      '#options' => $options,
      '#default_value' => array_keys($options),
      '#description' => t('Define where to look for usages of the image URI. In principle, all (long) text field_names should be searched for, but if, e.g, you are sure that none of the duplicate images are referred to in some text field, you may speed up this phase by deselecting that field.'),
    );

    $options = $this->getImageStyles();
    $form['options']['image_styles'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Image styles to check for in text field_names'),
      '#options' => $options,
      '#default_value' => array_keys($options),
      '#description' => t('Define what image style URIs to check for when searching text field_names. Normally, all image styles should be searched for. But if you are sure that some image styles are only used in image field formatters and are never used in (long) text field_names, you may speed up this phase by deselecting that image style. Looking for image derivative usages involves additional queries.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $save = $_POST;
    unset($save['form_build_id']);
    unset($save['form_token']);
    unset($save['form_id']);
    unset($save['op']);
    $_SESSION['duplicate_images'] = array_merge($_SESSION['duplicate_images'], $save);
    $_SESSION['duplicate_images']['selected_duplicate_images'] = array_filter($_SESSION['duplicate_images']['duplicate_images']);
    $_SESSION['duplicate_images']['selected_suspicious_images'] = array_filter($_SESSION['duplicate_images']['suspicious_images']);

    $duplicate_images = array_intersect_key($_SESSION['duplicate_images']['duplicate_images_mapping'], array_fill_keys($_SESSION['duplicate_images']['selected_duplicate_images'], 1));
    $suspicious_images = array_intersect_key($_SESSION['duplicate_images']['suspicious_images'], array_fill_keys($_SESSION['duplicate_images']['selected_suspicious_images'], 1));
    $managed_files = count(array_filter($_SESSION['duplicate_images']['managed_files'])) === 1;
    $fields_uri = array_filter($_SESSION['duplicate_images']['fields_uri']);
    $fields_media_tag = (!empty($_SESSION['duplicate_images']['fields_media_tag'])) ? array_filter($_SESSION['duplicate_images']['fields_media_tag']) : array();
    $image_styles = array_filter($_SESSION['duplicate_images']['image_styles']);
    $results = $this->exec($duplicate_images, $suspicious_images, $managed_files, $fields_uri, $fields_media_tag, $image_styles);
    $_SESSION['duplicate_images']['entity_update_instructions'] = $results[0];
    $_SESSION['duplicate_images']['duplicate_references'] = $results[1];
    $_SESSION['duplicate_images']['duplicate_managed_files'] = $results[2];
  }

  /**
   * Executes the find usages step.
   *
   * This step should result in a list of updates to perform for the selected
   * duplicates and selected suspicious images.
   *
   * We will have 2 sorts of updates:
   * - Via managed files.
   * - Via referencing images in (body) texts.
   *
   * Managed files updates can appear in 2 forms:
   * - The original image is stored as managed file and thus has a fid: update
   *   all file and image fields that refer to a managed duplicate to refer to
   *   the managed original.
   * - The original image is not yet stored as managed file: update (1) managed
   *   duplicate to refer to the original image (thus change of uri, not of fid)
   *   and update references other managed duplicates to refer to the updated
   *   managed duplicate.
   *
   * Textual references:
   * These will appear in value columns of fields. These references will have to
   * be replaced with references to the original.
   *
   * To assure that other modules get informed about our updates we will have to
   * update via the entity API. Meaning that our updates will need to specify
   * not only the entity (entity_type, entity_id) but also the field value
   * (field name, delta and language).
   *
   * @param string[] $duplicate_images
   *   List of duplicate => original pairs.
   * @param array[] $suspicious_images
   *   List of suspicious info arrays (array with keys: duplicate, original,
   *   reason) keyed by duplicate file name.
   * @param bool $managed_files
   *   Also search through managed files?
   * @param string[] $fields_uri
   *   List of field names to search through for URI usage.
   * @param array $fields_media_tag
   *   List of field names to search through for media tag (fid) usage.
   * @param string[] $image_styles
   *   List of image styles to search for referencing URIs.
   *
   * @return array[]
   *   3 arrays:
   *   - Update instructions. Multi dimensional array keyed by entity type,
   *     entity id, field/property name, [language, delta, and column name] and
   *     the new value or a list of replacements as value.
   *   - List of entities referring to the duplicates. 2-dimensional array
   *     keyed by entity type and id.
   *   - List of fid => duplicate pairs.
   */
  public function exec(array $duplicate_images, array $suspicious_images, $managed_files, array $fields_uri, array $fields_media_tag, array $image_styles) {
    $this->initStreamInfo();

    $field_infos_uri = array();
    foreach ($fields_uri as $field_name) {
      $field_infos_uri[$field_name] = field_info_field($field_name);;
      $field_infos_uri[$field_name]['search_columns'] = $this->getColumnsByFieldType($field_infos_uri[$field_name]['type']);
    }

    $field_infos_media_tag = array();
    foreach ($fields_media_tag as $field_name) {
      $field_infos_media_tag[$field_name] = field_info_field($field_name);;
      $field_infos_media_tag[$field_name]['search_columns'] = $this->getColumnsByFieldType($field_infos_media_tag[$field_name]['type']);
    }

    foreach ($duplicate_images as $duplicate => $original) {
      $this->findUsages($duplicate, $original, $managed_files, $field_infos_uri, $field_infos_media_tag, $image_styles);
    }
    foreach ($suspicious_images as $duplicate => $duplicate_info) {
      $this->findUsages($duplicate, $duplicate_info['original'], $managed_files, $field_infos_uri, $field_infos_media_tag, $image_styles);
    }
    return array($this->updateInstructions, $this->duplicateReferences, $this->duplicateManagedFiles);
  }

  /**
   * Finds all usages of a duplicate.
   *
   * For all usages found an update or replacement instruction is added to
   * $this->usage.
   *
   * @param string $duplicate
   *   Name of the duplicate image.
   * @param string $original
   *   Name of the original image.
   * @param bool $managed_files
   *   Also search in the managed files table.
   * @param array[] $field_infos_uri
   *   Array of field info arrays of fields to search for URI usage.
   * @param array $field_infos_media_tag
   *   Array of field info arrays of fields to search for media tag (fid) usage.
   * @param string[] $image_styles
   *   List of image styles to search for usages.
   */
  protected function findUsages($duplicate, $original, $managed_files, array $field_infos_uri, array $field_infos_media_tag, array $image_styles) {
    if ($managed_files) {
      $this->findUsagesAsManagedFile($duplicate, $original, $field_infos_media_tag);
    }

    foreach ($field_infos_uri as $field) {
      // Try to prevent time-outs by restarting the timer.
      @set_time_limit(ini_get('max_execution_time'));

      $this->findUriUsagesByField($field, $duplicate, $original, $image_styles);
    }
  }

  /**
   * Finds usages for the given duplicate.
   *
   * Update instructions are added to $this->usage.
   *
   * @param string $duplicate
   *   Name of the duplicate image.
   * @param string $original
   *   Name of the original image.
   * @param array $field_infos_media_tag
   *   List of field info arrays of fields to search for media tag (fid) usage.
   */
  protected function findUsagesAsManagedFile($duplicate, $original, array $field_infos_media_tag) {
    // Find the managed file object (at most 1 object as 'uri' is unique) for
    // the duplicate.
    $managed_duplicate = file_load_multiple(array(), array('uri' => $duplicate));
    $managed_duplicate = reset($managed_duplicate);
    if ($managed_duplicate !== FALSE) {
      // Find the managed file fid for the original, if not already found.
      if (!isset($this->originalIds[$original])) {
        $managed_original = file_load_multiple(array(), array('uri' => $original));
        $managed_original = reset($managed_original);
        if ($managed_original !== FALSE) {
          $this->originalIds[$original] = $managed_original->fid;
        }
      }

      if (isset($this->originalIds[$original])) {
        // All references to the fid of $managed_duplicate will be updated to
        // refer to the fid of the $original. So this record itself does not
        // have to be updated and can be deleted after the update phase when no
        // references to it remain.
        $this->duplicateManagedFiles[$managed_duplicate->fid] = $duplicate;
        $this->findUsagesOfManagedFile($managed_duplicate, $this->originalIds[$original], $field_infos_media_tag);
      }
      else {
        // The $original has not yet been registered as a managed file. We will
        // update the uri field of the $managed_duplicate record to point to
        // $original.
        // This also means that references to the fid of this record can remain
        // unchanged. So no need to add updates instructions for file/image
        // fields for this fid.
        $this->updateInstructions['file'][$managed_duplicate->fid]['uri'] = $original;
        $this->duplicateReferences['file'][$managed_duplicate->fid][$duplicate] = $duplicate;

        // To prevent updating the uri of other duplicates to the same
        // $original, we register the fid of this duplicate as the fid for the
        // $original.
        $this->originalIds[$original] = $managed_duplicate->fid;
      }
    }
  }

  /**
   * Finds the entities referring to this managed file.
   *
   * These references come from either:
   * - File fields
   * - Image fields
   * - Custom managed file reference fields.
   * - Media tags in (long) text fields. These will look like
   *   [[{"fid":"12345","view_mode":"media_large", ...}]]
   *
   * Update instructions are added to $this->usage.
   *
   * @param object $managed_duplicate
   *   Managed file record for a duplicate image.
   * @param int $original_fid
   *   Fid of the original.
   * @param array $field_infos_media_tag
   *   List of field info arrays of fields to search for media tag (fid) usage.
   */
  protected function findUsagesOfManagedFile($managed_duplicate, $original_fid, array $field_infos_media_tag) {
    $this->findUsagesByUserPicture($managed_duplicate->fid, $original_fid, $managed_duplicate->uri);
    foreach ($this->getFieldsReferringToManagedFiles() as $field) {
      $this->findFidUsagesByField($field, $managed_duplicate->fid, $original_fid);
    }

    foreach ($field_infos_media_tag as $field_info) {
      $this->findMediaTagUsagesByField($field_info, $managed_duplicate->fid, $original_fid);
    }
  }

  /**
   * Finds usages of a managed file in the user picture property.
   *
   * @param int $duplicate_fid
   *   Fid of the duplicate.
   * @param int $original_fid
   *   Fid of the original.
   * @param string $duplicate
   *   Name of the duplicate being searched for.
   */
  protected function findUsagesByUserPicture($duplicate_fid, $original_fid, $duplicate) {
    $table_name = 'users';
    /** @noinspection PhpUndefinedMethodInspection */
    $uids = db_select($table_name)
      ->fields($table_name, array('uid'))
      ->condition('picture', $duplicate_fid)
      ->execute()
      ->fetchCol();
    foreach ($uids as $uid) {
      $this->updateInstructions['user'][$uid]['picture'] = $original_fid;
      $this->duplicateReferences['user'][$uid][$duplicate] = $duplicate;
    }
  }

  /**
   * Finds entities for which the field is referring to the fid of a duplicate.
   *
   * The search is done in only some of the columns of the specified field. The
   * search is also done on URIs for images derived form the original via an
   * image style.
   *
   * Update instructions are added to $this->usage.
   *
   * @param array $field_info
   *   Field info array + an additional column 'search_columns'.
   * @param int $duplicate
   *   Fid to search for.
   * @param int $original
   *   Fid to replace the found value with. Note that this step
   *   does not actually update, This parameter is used to create an "update
   *   instruction".
   */
  protected function findFidUsagesByField($field_info, $duplicate, $original) {
    foreach ($field_info['search_columns'] as $column) {
      $this->findUsagesByFieldColumn($field_info, $column, TRUE, $duplicate, $original, $duplicate);
    }
  }

  /**
   * Finds entities for which the field is referring to a URI of a duplicate.
   *
   * The field is only searched for in the columns specified in
   * $field_info[search_columns]. Those columns are searched for:
   * 1 The file name in scheme notation, e.g. pubic://my-image_0.jpg.
   * 2 The file as external URL, e.g. sites/default/files/my-image_0.jpg.
   * 3 External URLs for derived images, e.g.
   *   sites/default/files/styles/medium/public/my-image_0.jpg.
   *
   * Update instructions are added to $this->usage.
   *
   * @param array $field_info
   *   Field info array + an additional column 'search_columns'.
   * @param string $duplicate
   *   File name, in scheme notation, to search for.
   * @param string $original
   *   File name, in scheme notation, to replace the found value with. Note that
   *   this step does not actually update, This parameter is used to create an
   *   "update instruction".
   * @param string[] $image_styles
   *   List of image styles to search for derived usages.
   */
  protected function findUriUsagesByField($field_info, $duplicate, $original, array $image_styles) {
    /** @var DrupalLocalStreamWrapper $duplicate_stream */
    $scheme = file_uri_scheme($duplicate);
    $base_url = $this->streamInfo[$scheme]['base_url'];
    $duplicate_target = file_uri_target($duplicate);
    $duplicate_uri = $base_url . $duplicate_target;
    $original_target = file_uri_target($original);
    $original_uri = $base_url . $original_target;

    $info = image_get_info($duplicate);
    $is_image = $info && !empty($info['extension']);

    foreach ($field_info['search_columns'] as $column) {
      // 1 The file name in scheme notation.
      $this->findUsagesByFieldColumn($field_info, $column, FALSE, $duplicate, $original, $duplicate);

      // 2 The file as external URL.
      $this->findUsagesByFieldColumn($field_info, $column, FALSE, $duplicate_uri, $original_uri, $duplicate);

      // 3 External URLs for derived images.
      // Using the insert module (or for that matter any module that inserts
      // img or link tags in text), the reference may also be to a
      // derivative image of the duplicate. This will have the form:
      // {stream_base_url}/styles/{style_name}/scheme/path.
      if ($is_image) {
        foreach ($image_styles as $style_name) {
          $duplicate_style_uri = $base_url . 'styles/' . $style_name . '/' . $scheme . '/' . $duplicate_target;
          $original_style_uri = $base_url . 'styles/' . $style_name . '/' . $scheme . '/' . $original_target;

          $this->findUsagesByFieldColumn($field_info, $column, FALSE, $duplicate_style_uri, $original_style_uri, $duplicate);
        }
      }
    }
  }

  /**
   * Finds entities for which the field is referring to the fid of a duplicate.
   *
   * The field is only searched for in the columns specified in
   * $field_info[search_columns]. Those columns are searched for:
   * 1 The fid as it appears as first field in a media tag, e.g.
   *   [[{"fid":"12345","view_mode":"media_large", ...}]]
   * 2 The fid as it appears as next field in a media tag, e.g.
   *   [[{...,"fid":"12345","view_mode":"media_large", ...}]]
   * Note: so far, we have not seen any occurrences where the fid field was the
   *   last field in a media tag.
   *
   * Update instructions are added to $this->usage.
   *
   * @param array $field_info
   *   Field info array + an additional column 'search_columns'.
   * @param int $duplicate
   *   Fid to search for.
   * @param int $original
   *   Fid to replace the found value with. Note that this step does not
   *   actually update, This parameter is used to create an "update
   *   instruction".
   */
  protected function findMediaTagUsagesByField($field_info, $duplicate, $original) {
    $search = sprintf('[[{"fid":"%d",', $duplicate);
    $replace = sprintf('[[{"fid":"%d",', $original);
    foreach ($field_info['search_columns'] as $column) {
      $this->findUsagesByFieldColumn($field_info, $column, FALSE, $search, $replace, $duplicate);
    }
    $search = sprintf(',"fid":"%d",', $duplicate);
    $replace = sprintf(',"fid":"%d",', $original);
    foreach ($field_info['search_columns'] as $column) {
        $this->findUsagesByFieldColumn($field_info, $column, FALSE, $search, $replace, $duplicate);
    }
  }

  /**
   * Find usages of a given string value in a given column of a given field.
   *
   * Adds a list of replacement instructions to $this->usages.
   *
   * @param array $field
   *   Field info of the field to search in.
   * @param string $column
   *   Column within the field to search in.
   * @param bool $exact
   *   Whether the search is exact, for the whole value of the field, or
   *   partial, i.e. the (string )value is contained within the (string) field
   *   value.
   * @param string|int $value
   *   Fid or file name to search for.
   * @param string|int $replace
   *   Fid or file name to replace the found value with. Note that this step
   *   does not actually update, This parameter is used to create an "update
   *   instruction".
   * @param string $duplicate
   *   The duplicate file that $value is referring to.
   */
  protected function findUsagesByFieldColumn($field, $column, $exact, $value, $replace, $duplicate) {
    if ($exact) {
      $operator = '=';
      $condition_value = $value;
    }
    else {
      $operator = 'LIKE';
      $condition_value = '%' . $value . '%';
    }
    $table_name = _field_sql_storage_tablename($field);
    $column_name = _field_sql_storage_columnname($field['field_name'], $column);
    $query_result = db_select($table_name)
      ->fields($table_name, array('entity_type', 'entity_id', 'language', 'delta'))
      ->condition('deleted', 0)
      ->condition($column_name, $condition_value, $operator)
      ->execute();
    foreach ($query_result as $row) {
      if ($exact) {
        $this->updateInstructions[$row->entity_type][$row->entity_id][$field['field_name']][$row->language][$row->delta][$column] = $replace;
      }
      else {
        // We must distinguish search and replace from plain updates. So we use
        // an array for search and replace (as compared to a scalar value) as
        // this also allows us to have multiple search and replaces per block of
        // text which is possible.
        $this->updateInstructions[$row->entity_type][$row->entity_id][$field['field_name']][$row->language][$row->delta][$column][$value] = $replace;
      }
      // Keep track of which entities refer to which duplicates, so we can
      // prevent deleting duplicates when some entities fail to update. Key by
      // $duplicate to prevent adding the same value multiple times.
      $this->duplicateReferences[$row->entity_type][$row->entity_id][$duplicate] = $duplicate;
    }
  }

  /**
   * Helper method that captures knowledge of specific field types.
   *
   * @param string $field_type
   *   Field type.
   *
   * @return string[]
   *   A list of field columns to search for occurrences of the file names.
   */
  protected function getColumnsByFieldType($field_type) {
    switch ($field_type) {
      case 'link_field':
        $result = array('url');
        break;

      case 'text_with_summary':
        $result = array('value', 'summary');
        break;

      case 'text':
      case 'text_long':
      default:
        $result = array('value');
        break;

    }
    return $result;
  }

  /**
   * Returns a list of fields that are referring to managed files.
   *
   * @return array[]
   *   A keyed array of field_names as key and the field info as values. The
   *   field info contains an extra key 'search_columns' that contains the
   *   column that refers to the fid of the managed file.
   */
  protected function getFieldsReferringToManagedFiles() {
    if ($this->fieldsReferringManagedFiles === NULL) {
      $this->fieldsReferringManagedFiles = array();
      $fields = field_info_fields();
      foreach ($fields as $field) {
        $column = $this->fileFieldFindFileReferenceColumn($field);
        if ($column !== FALSE) {
          $this->fieldsReferringManagedFiles[$field['field_name']] = $field;
          $this->fieldsReferringManagedFiles[$field['field_name']]['search_columns'] = array($column);
        }
      }
    }
    return $this->fieldsReferringManagedFiles;
  }

  /**
   * NOTE: Copied from patch in [#1805690]! and already available as such in D8.
   *
   * Determine whether a field references files stored in {file_managed}.
   *
   * @param array $field
   *   A field definition array.
   *
   * @return string|false
   *   The field column if the field references {file_managed}.fid, typically
   *   fid, FALSE if it does not.
   */
  protected function fileFieldFindFileReferenceColumn($field) {
    // I think that $field['foreign keys'] should be set, but we got reports
    // about "Notice: Undefined index: foreign keys in...".
    if (isset($field['foreign keys'])) {
      foreach ($field['foreign keys'] as $data) {
        if ($data['table'] == 'file_managed') {
          foreach ($data['columns'] as $field_column => $column) {
            if ($column === 'fid') {
              return $field_column;
            }
          }
        }
      }
    }
    return FALSE;
  }

  /**
   * Initializes the stream info property.
   */
  protected function initStreamInfo() {
    /** @var DrupalLocalStreamWrapper $stream */
    $stream = file_stream_wrapper_get_instance_by_scheme('public');
    $this->streamInfo['public'] = array(
      'base_url' => $stream->getDirectoryPath() . '/',
      'style_url' => '/public/',
    );

    $this->streamInfo['private'] = array(
      'base_url' => 'system/files/',
      'style_url' => '/private/',
    );
  }

  /**
   * Returns a list of image styles as options array.
   *
   * @return array
   *   List of image styles as options array.
   */
  protected function getImageStyles() {
    $image_styles = image_styles();
    $options = array();
    foreach ($image_styles as $image_style) {
      $options[$image_style['name']] = $image_style['label'];
    }
    return $options;
  }

  /**
   * Returns a list of field types that may contain image URIs in text.
   *
   * @return array
   *   Array of field type labels. Keyed by the machine name of the field type.
   */
  protected function getFieldsThatMayBeSearched() {
    $options = array();
    $field_types = $this->getFieldTypesThatMayBeSearched();
    foreach ($field_types as $field_type) {
      $options = array_merge($options, $this->getFieldsByType($field_type));
    }
    return $options;
  }

  /**
   * Returns a list of field types that may contain image URIs in text.
   *
   * @return array
   *   Array of field type labels. Keyed by the machine name of the field type.
   */
  protected function getFieldTypesThatMayBeSearched() {
    $options = array();
    if (module_exists('text')) {
      $options[] = 'text';
      $options[] = 'text_long';
      $options[] = 'text_with_summary';
    }
    if (module_exists('link')) {
      $options[] = 'link_field';
    }
    return $options;
  }

  /**
   * Returns all fields that are of any of the given types.
   *
   * @param string $field_type
   *   Field type.
   *
   * @return array[]
   *   List of field info arrays.
   */
  protected function getFieldsByType($field_type) {
    $result = array();
    $fields = field_info_fields();
    foreach ($fields as $field_info) {
      if ($field_info['type'] === $field_type) {
        $label = $field_info['field_name'] . ' (' . $field_info['type'] . ') ' . t('used in:');
        foreach ($field_info['bundles'] as $entity_type => $bundles) {
          $label .= ' ';
          if (count($bundles) === 1 && $entity_type === reset($bundles)) {
            $label .= $entity_type;
          }
          else {
            $label .= $entity_type . ' (' . implode(', ', $bundles) . ')';
          }
          $label .= ';';
        }
        $result[$field_info['field_name']] = $label;
      }
    }
    return $result;
  }

}
