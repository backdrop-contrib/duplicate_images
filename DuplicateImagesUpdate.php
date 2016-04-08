<?php

/**
 * Class DuplicateImagesUpdate
 */
class DuplicateImagesUpdate extends DuplicateImagesBaseForm {

  /**
   * {@inheritdoc}
   */
  protected function getButton() {
    return t('Update');
  }

  /**
   * {@inheritdoc}
   */
  protected function getHelp() {
    return t('Update usages') . ': ' . t('Updates the found usages to use the original image. The result will be that duplicate images are no longer referred to. Updates are done using the function entity_save(), so caches should be cleared, file usage updated, rules executed, etc.');
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

    $update_instructions = $form_state['entity_update_instructions'];
    $duplicate_references = $form_state['duplicate_references'];
    $duplicate_managed_files = $form_state['duplicate_managed_files'];
    $updates = array();
    foreach ($update_instructions as $entity_type => $entities) {
      foreach ($entities as $entity_id => $entity) {
        $fields = implode(', ', array_keys($entity));
        if (isset($duplicate_references[$entity_type][$entity_id])) {
          $duplicates = implode(', ', $duplicate_references[$entity_type][$entity_id]);
        }
        else {
          $duplicates = $duplicate_managed_files[$entity_id];
        }
        $updates["$entity_type $entity_id"] = "$entity_type $entity_id:<br>"
          . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . t('field(s): ') . $fields . '<br>'
          . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . t('duplicate(s) referred: ') . $duplicates;
      }
    }

    $form['results']['entities_to_update'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Updates') . ' (' . count($updates) . ')',
      '#options' => $updates,
      '#default_value' => array_keys($updates),
      '#description' => t('These are the entities that need to be updated to remove all references to the selected duplicate images.'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    parent::submit($form, $form_state);

    $form_state['selected_entities_to_update'] = array_filter($form_state['values']['entities_to_update']);
    $entity_update_instructions = $form_state['entity_update_instructions'];
    $entitiesToUpdate = array();
    foreach ($entity_update_instructions as $entity_type => $entities) {
      foreach ($entities as $entity_id => $entity) {
        if (array_key_exists("$entity_type $entity_id", $form_state['selected_entities_to_update'])) {
          $entitiesToUpdate[$entity_type][$entity_id] = $entity;
        }
        else {
          
        }
      }
    }
    $form_state['updates_performed'] = $this->exec($entitiesToUpdate);
  }

  /**
   * Updates the entities per the "instructions"
   *
   * @param array[] $entitiesToUpdate
   *  A multidimensional array containing a list of entity update instructions.
   *  These are keyed by entity type and entity id.
   * 
   *  The instructions are also multidimensional arrays keyed by field name,
   *  delta and language and then containing the new value or a list of
   *  replacements to make 
   * 
   *  File entities do not have cardinality nor are translatable, so there the
   *  keys delta and language are not available, not can the values be a list of
   *  replacements.
   *
   * @return array[]
   */
  public function exec(array $entitiesToUpdate) {
    $result = array();

    foreach ($entitiesToUpdate as $entity_type => $entityUpdates) {
      $entities = entity_load($entity_type, array_keys($entityUpdates));
      foreach ($entityUpdates as $entity_id => $field_updates) {
        $result["$entity_type $entity_id"] = $this->updateEntity($entity_type, $entities[$entity_id], $field_updates);

        // Try to prevent time-outs by restarting the timer.
        @set_time_limit(ini_get('max_execution_time'));
      }
    }
    return $result;
  }

  /**
   * Updates the given fields of 1 entity.
   *
   * @param string $entity_type
   * @param object $entity
   * @param array $field_updates
   *
   * @return bool
   *   The result of entity_save();
   */
  protected function updateEntity($entity_type, $entity, $field_updates) {
    foreach ($field_updates as $field_name => $field_update) {
      if (is_array($field_update)) {
        foreach ($field_update as $language => $deltas) {
          foreach ($deltas as $delta => $columns) {
            foreach ($columns as $column => $new_value) {
              if (is_array($new_value)) {
                // Array: search for the keys and replace them with the values.
                $entity->{$field_name}[$language][$delta][$column] = strtr($entity->{$field_name}[$language][$delta][$column], $new_value);
              }
              else {
                // Scalar value: replace the whole value with the new value.
                $entity->{$field_name}[$language][$delta][$column] = $new_value;
              }
            }
          }
        }
      }
      else {
        // For File and User entities, we are updating properties here, so no
        // multiple languages, nor deltas, nor field columns.
        $entity->{$field_name} = $field_update;
      }
    }
    return entity_save($entity_type, $entity) !== FALSE;
  }

}