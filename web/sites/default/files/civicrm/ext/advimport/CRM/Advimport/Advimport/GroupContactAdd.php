<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_GroupContactAdd extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("Add Contact to Group");
  }

  /**
   * Internal helper
   */
  private function getUserInputFields() {
    return [
      'group_id' => [
        'label' => E::ts('Group'),
        'field' => 'group_id',
        'type' => 'select'
      ],
      'contact_match' => [
        'label' => E::ts('Contact matching'),
        'field' => 'contact_match',
        'type' => 'select'
      ]
    ];
  }

  /**
   * Available fields.
   */
  public function getMapping(&$form) {
    return [
/* @todo or remove? could be useful for a quick "importer newsletter contacts" */
/*
      'email' => [
        'label' => E::ts('Email'),
        'field' => 'email',
      ],
*/
      'contact_id' => [
        'label' => E::ts('Contact ID'),
        'field' => 'contact_id',
        'aliases' => ['contact_id'],
      ],
    ];
  }

  /**
   *
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
    switch ($fieldParams['field']) {
      case 'group_id':
        $options = [0 => ts('- select -')] + (Array) \Civi\Api4\Group::get()
          ->addSelect('title')
          ->addWhere('is_active', '=', TRUE)
          ->addWhere('is_hidden', '=', FALSE)
          ->addWhere('saved_search_id', 'IS NULL')
          ->execute()
          ->indexBy('id')
          ->column('title');
        break;

      case 'contact_match':
        $options = [
          'contact_id' => E::ts('Contact ID'),
          // @todo or remove?
          // 'email' => E::ts('E-Mail - create contact if not found'),
        ];
        break;
    }

    return $options ?? [];
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

    $form->assign('mapfield_instructions', E::ts('If a contact is already added to the group, they will be ignored. If their status was "removed" or "pending", they will be changed to "added".'));
  }

  /**
   * Check if we have all the data we need.
   */
  function mapfieldsPostProcessPre(&$form) {
    // @todo check contact_id
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
    $fields = $this->getUserInputFieldsValues($params);

    if (empty($params['contact_id'])) {
      throw new Exception('Missing contact_id');
    }

    $contact = \Civi\Api4\Contact::get()
      ->addSelect('id')
      ->addWhere('id', '=', $params['contact_id'])
      ->execute()
      ->single();

    $group_contact = \Civi\Api4\GroupContact::get()
      ->addWhere('group_id', '=', $fields['group_id'])
      ->addWhere('contact_id', '=', $contact['id'])
      ->execute()
      ->first();

    if (!empty($group_contact)) {
      if ($group_contact['status'] != 'Added') {
        \Civi\Api4\GroupContact::update()
          ->addValue('status', 'Added')
          ->setTracking('Advimport')
          ->setMethod('API')
          ->addWhere('id', '=', $group_contact['id'])
          ->execute();
      }

      CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_group_contact', $group_contact['id']);
      CRM_Advimport_Utils::addContactToGroupOrTag($contact['id'], $params);
      return;
    }

    $result = \Civi\Api4\GroupContact::create()
      ->addValue('group_id', $fields['group_id'])
      ->addValue('contact_id', $contact['id'])
      ->addValue('status:name', 'Added')
      ->setTracking('Advimport')
      ->setMethod('API')
      ->execute()
      ->first();

    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_group_contact', $result['id']);
    CRM_Advimport_Utils::addContactToGroupOrTag($contact['id'], $params);
  }

}
