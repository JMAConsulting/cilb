<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_APIv3 extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Available fields.
   */
  function getMapping(&$form) {
    $map = [];

    // QuickForm weirdness: this gets called before setDefaults
    // and also gets called twice (the second time will have empty values).
    if (!isset($form)) {
      return;
    }

    $api3_entity = $form->controller->get('api3_entity');

    if (!$api3_entity) {
      $values = $form->exportValues();
      $api3_entity = $values['api3_entity'];
    }

    if ($api3_entity) {
      $form->controller->set('api3_entity', $api3_entity);

      $fields = civicrm_api3($api3_entity, 'getfields', [
        'sequential' => 1,
      ])['values'];

      foreach ($fields as $val) {
        $name = $val['name'];

        $map[$name] = [
          'label' => $val['title'] . ($val['required'] ? ' *' : ''),
          'field' => $name,
          'required' => $val['required'],
          'aliases' => [
            // Helps to match on the machine name (first_name), not just 'First Name'
            $val['name'],
          ],
	  // @todo For this to work, we need to fix how api3 AdvimportRow.Field fetches the mapping
	  // and we need to save the api3 entity in the mapping.
	  'bulk_update' => true,
        ];
      }

      if (in_array($api3_entity, ['Contribution', 'Participant'])) {
        $map['external_identifier'] = [
          'label' => ts('External ID'),
          'field' => 'external_identifier',
          'required' => 0,
          'aliases' => [
            // Helps to match on the machine name (first_name), not just 'First Name'
            'External ID',
            'external_id',
          ],
        ];
      }
    }

    return $map;
  }

  /**
   *
   */
  function mapfieldsSetDefaultValues(&$form) {
    $values = [];

    $values['api3_entity'] = $form->controller->get('api3_entity');

    if (empty($values['api3_entity'])) {
      $advimport_id = $form->controller->get('advimport_id');

      if ($advimport_id) {
        $mapping = $form->controller->get('mapping');

        if (!empty($mapping['api3_entity'])) {
          $api3_entity = $mapping['api3_entity'];
          $form->controller->set('api3_entity', $api3_entity);
          $values['api3_entity'] = $api3_entity;
        }
      }
    }

    return $values;
  }

  /**
   * Alter MapFields form.
   */
  function mapfieldsBuildFormPre(&$form) {
    $options = [];
    $options[0] = ts('- select -');

    $entities = civicrm_api3('Entity', 'get', [
      'sequential' => 1,
    ])['values'];

    foreach ($entities as $val) {
      $options[$val] = $val;
    }

    $form->add('select', 'api3_entity', E::ts('Entity'), $options, TRUE);
  }

  /**
   * Check if we have all the data we need.
   */
  function mapfieldsPostProcessPre(&$form) {
    $api3_entity = $form->controller->get('api3_entity');

    if (!$api3_entity) {
      return false;
    }

    $mapping = $form->controller->exportValues('MapFields');

    $has_data = false;
    $missing_required = false;
    $ignore = ['qfKey', 'entryURL', 'api3_entity'];

    foreach ($mapping as $entity_field => $column) {
      if (!in_array($entity_field, $ignore)) {
        $has_data = true;
      }
    }

    if (!$has_data) {
      return false;
    }

    // Check for required fields
    $fields = civicrm_api3($api3_entity, 'getfields', [
      'sequential' => 1,
    ])['values'];

    foreach ($fields as $val) {
      $name = $val['name'];

      if ($val['required'] && $name != 'id' && !in_array($name, $mapping)) {
        // If we have the external_identifier but not the contact_id, proceed anyway (ex: contributions)
        if ($name == 'contact_id' && array_search('external_identifier', $mapping) !== FALSE) {
          continue;
        }
        $missing_required = true;
        CRM_Core_Session::setStatus(E::ts("%1 (%2) is a required field. The field must be in the uploaded file and you must select a field match for it.", [1 => $val['title'], 2 => $name]), 'error');
      }
    }

    if ($missing_required) {
      return false;
    }

    return true;
  }

  /**
   * Returns a human-readable name for this helper.
   */
  function getHelperLabel() {
    return E::ts("APIv3 Entity");
  }
  
  /**
   * Import an item gotten from the queue.
   */
  function processItem($params) {
    $api3_entity = CRM_Utils_Array::value('api3_entity', $params);
    unset($params['api3_entity']);

    // Hack to fetch this data, until MapFields is fixed
    // Currently the above fails because api3_entity is not in params.
    if (!$api3_entity) {
      $mapping = CRM_Core_DAO::singleValueQuery('SELECT mapping FROM civicrm_advimport WHERE table_name = %1', [
        1 => [$params['import_table_name'], 'String'],
      ]);

      $mapping = json_decode($mapping, TRUE);
      $api3_entity = CRM_Utils_Array::value('api3_entity', $mapping);
    }

    if (in_array($api3_entity, ['Contribution', 'Participant']) && empty($params['contact_id']) && !empty($params['external_identifier'])) {
      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id')
        ->addWhere('external_identifier', '=', $params['external_identifier'])
        ->execute()
        ->first();

      if ($contact) {
        $params['contact_id'] = $contact['id'];
      }
    }

    $result = civicrm_api3($api3_entity, 'create', $params);
    CRM_Advimport_Utils::setEntityTableAndId($params, $api3_entity, $result['id']);
  }

}
