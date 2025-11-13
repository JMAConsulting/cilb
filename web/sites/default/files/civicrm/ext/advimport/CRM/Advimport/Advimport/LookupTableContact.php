<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_LookupTableContact extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts('Lookup Table to Batch Update Contacts');
  }

  /**     
   * Available fields.
   */     
  function getMapping(&$form) {
    // QuickForm weirdness: this gets called before setDefaults
    // and also gets called twice (the second time will have empty values).
    if (!isset($form)) {
      return;
    }

    return [
      'old_field' => [
        'label' => E::ts('Old Field'),
        'field' => 'old_field',
      ],
      'new_field' => [
        'label' => E::ts('New Field'),
        'field' => 'new_field',
      ],
    ];
  }

  /**
   * Internal helper
   */
  private function getUserInputFields() {
    return [
      'old_field' => [
        'label' => E::ts('Old Field'),
        'field' => 'old_field',
        'type' => 'select'
      ],
      'new_field' => [
        'label' => E::ts('New Field'),
        'field' => 'new_field',
        'type' => 'select'
      ],
      'match_type' => [
        'label' => E::ts('Match Type'),
        'field' => 'match_type',
        'type' => 'select'
      ],
    ];
  }

  /**
   * @todo Why is this necessary? or not in the base class?
   */
  function mapfieldsSetDefaultValues(&$form) {
    $values = [];

    $advimport_id = $form->controller->get('advimport_id');
    $fields = $this->getUserInputFields();

    if (!$advimport_id) {
      return $values;
    }

    $mapping = $form->controller->get('mapping');

    foreach ($fields as $f => $discard) {
      $values[$f] = $form->controller->get($f);

      if (empty($values[$f])) {
        if (!empty($mapping[$f])) {
          $val = $mapping[$f];
          $form->controller->set($f, $val);
          $values[$f] = $val;
        }
      }
    }

    return $values;
  }

  private function getFieldOptions($fieldParams) {
    $option = [];

    switch ($fieldParams['field']) {
      case 'old_field':
      case 'new_field':
        $options = (array) \Civi\Api4\Contact::getFields(FALSE)
          ->addSelect('name', 'label')
          ->execute()
          ->indexBy('name')
          ->column('label');
        break;

      case 'match_type':
        $options = [
          1 => E::ts('Option Value'),
          2 => E::ts('Option Label'),
        ];
        break;
    }

    return $options;
  }

  /**
   * Alter MapFields form.
   *
   * @param CRM_Core_Form $form
   */
  function mapfieldsBuildFormPre(&$form) {
    $fields = $this->getUserInputFields();
    foreach ($fields as $field => $fieldParams) {
      switch ($field) {
        default:
          $form->add($fieldParams['type'], $fieldParams['field'], $fieldParams['label'], $this->getFieldOptions($fieldParams));
          break;
      }
    }

    $form->assign('mapfield_instructions', E::ts('Match Type only applies to fields with predefined values (select, checkbox, etc). After submitting this form, it will do a dummy processing of the Lookup Table. It will also create a new import for the data that needs to be updated. So once the next step is finished, go back to the Advanced Import main screen, and run the new import that will have been created (todo: one day, be more clever and automatically redirect to that step).'));
  }

  /**
   * Check if we have all the data we need.
   */
  function mapfieldsPostProcessPre(&$form) {
    // Create a new advimport with all contacts that have a value
    $formValues = $form->exportValues();
    $data = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', $formValues['old_field'] . ':label')
      ->addWhere($formValues['old_field'], 'IS NOT NULL')
      ->addWhere($formValues['old_field'], 'IS NOT EMPTY')
      ->execute();

    $lookupTableName = $form->controller->get('tableName');
    $old_field_column = array_search('old_field', $formValues);
    $new_field_column = array_search('new_field', $formValues);

    if (!$old_field_column) {
      throw new Exception(E::ts('Could not find the old_field column.'));
    }
    if (!$new_field_column) {
      throw new Exception(E::ts('Could not find the new_field column.'));
    }

    foreach ($data as &$d) {
      $old_value = $d[$formValues['old_field'] . ':label'];
      $new_value = CRM_Core_DAO::singleValueQuery('SELECT %1 FROM %2 WHERE %3 = %4', [
        1 => [$new_field_column, 'MysqlColumnNameOrAlias'],
        2 => [$lookupTableName, 'MysqlColumnNameOrAlias'],
        3 => [$old_field_column, 'MysqlColumnNameOrAlias'],
        4 => [$old_value, 'String'],
      ]);
      // This is to match the headers, which will always be old_field, new_field
      // and we will need to rely on the "Match Type" toggle later on
      $d['entity_table'] = 'civicrm_contact';
      $d['entity_id'] = $d['id'];
      $d['old_field'] = $old_value;
      $d['new_field'] = $new_value;
    }

    $headers = ['entity_table', 'entity_id', 'old_field', 'new_field'];
    $tableName = CRM_Advimport_BAO_Advimport::saveToDatabaseTable($headers, $data);

    $mapping = [
      'old_field' => $formValues['old_field'],
      'new_field' => $formValues['new_field'],
      'match_type' => $formValues['match_type'],
    ];

    $result = \Civi\Api4\Advimport::create(FALSE)
      ->addValue('contact_id', CRM_Core_Session::singleton()->get('userID'))
      ->addValue('classname', 'CRM_Advimport_Advimport_LookupTableContactProcess')
      ->addValue('table_name', $tableName)
      ->addValue('mapping', json_encode($mapping))
      ->addValue('track_entity_type', $form->controller->get('group_or_tag'))
      ->addValue('track_entity_id', $form->controller->get('group_or_tag_id'))
      ->execute()
      ->first();

    // $form->controller->set('advimport_id', $result['id']);
    return true;
  }

  /**
   * Get the saved mapping and map to a set of fields (so we know what the user selected)
   * @param array $params
   *
   * @return array
   */
  private function getUserInputFieldsValues($params) {
    if (!isset(\Civi::$statics[__CLASS__]['userinputfieldmapping'])) {
      $mapping = CRM_Core_DAO::singleValueQuery('SELECT mapping FROM civicrm_advimport WHERE table_name = %1', [
        1 => [$params['import_table_name'], 'String'],
      ]);
      $mapping = json_decode($mapping, TRUE);
      foreach ($this->getUserInputFields() as $field => $discard) {
        $fields[$field] = $params[$field] ?? $mapping[$field] ?? NULL;
      }
      \Civi::$statics[__CLASS__]['userinputfieldmapping'] = $fields;
    }
    return \Civi::$statics[__CLASS__]['userinputfieldmapping'];
  }

  /**
   * Import an item gotten from the queue.
   */
  function processItem($params) {
    // Nothing to do
  }

  function postImport($params) {
    // @todo Redirect to the processor
  }

}
