<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_Phone2Action extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("Contact + Activity");
  }

  /**
   * Internal helper
   */
  private function getUserInputFields() {
    return [
      'activity_type_id' => [
        'label' => E::ts('Activity Type'),
        'field' => 'activity_type_id',
        'type' => 'select'
      ],
      'tag_contact' => [
        'label' => E::ts('Add a tag using the "Activity Subject" field as tag name'),
        'field' => 'tag_contact',
        'type' => 'checkbox',
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
      'email' => [
        'label' => E::ts('Email'),
        'field' => 'email',
      ],
      'first_name' => [
        'label' => E::ts('First Name (or "Display Name" when matching on display name)'),
        'field' => 'first_name',
        'aliases' => ['display_name'],
      ],
      'last_name' => [
        'label' => E::ts('Last Name'),
        'field' => 'last_name',
      ],
      'phone' => [
        'label' => E::ts('Phone'),
        'field' => 'phone',
      ],
      'country' => [
        'label' => E::ts('Country'),
        'field' => 'country',
      ],
      'street_address_1' => [
        'label' => E::ts('Street Address line 1'),
        'field' => 'street_address_1',
        'aliases' => ['address1'],
      ],
      'street_address_2' => [
        'label' => E::ts('Street Address line 2'),
        'field' => 'street_address_2',
        'aliases' => ['address2'],
      ],
      'city' => [
        'label' => E::ts('City'),
        'field' => 'city',
      ],
      'zip_code' => [
        'label' => E::ts('Postal/Zip Code'),
        'field' => 'zip_code',
        'aliases' => ['postalcode', 'postcode'],
      ],
      'state' => [
        'label' => E::ts('State'),
        'field' => 'state',
        'aliases' => ['county']
      ],
      'source' => [
        'label' => E::ts('Activity Subject'),
        'field' => 'source',
        'aliases' => ['subject'],
      ],
      'activity_date' => [
        'label' => E::ts('Activity Date'),
        'field' => 'activity_date',
        'aliases' => ['date', 'Date'],
        'description' => 'The activity date (eg. 20210615121500 for 15th June 2021 at 12:15)',
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
      case 'activity_type_id':
        // Create an Activity
        $atypes = civicrm_api3('OptionValue', 'get', [
          'option_group_id' => 'activity_type',
          'sequential' => 1,
          'is_reserved' => 0,
          'options' => ['limit' => 0],
        ])['values'];

        $options = [];
        $options[0] = ts('- select -');

        foreach ($atypes as $val) {
          $options[$val['value']] = $val['label'];
        }
        break;

      case 'contact_match':
        $options = [
          'email' => E::ts('Match on email (contact will be created if not found)'),
          'display_name' => E::ts("Match on 'Display Name' (contact will NOT be created if not found)"),
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
        case 'tag_contact':
          // @fixme Can we do this via $form->add() as well?
          $form->addElement($fieldParams['type'], $fieldParams['field'], $fieldParams['label']);
          break;

        default:
          $form->add($fieldParams['type'], $fieldParams['field'], $fieldParams['label'], $this->getFieldOptions($fieldParams));
          break;

      }
    }

    $form->assign('mapfield_instructions',
      E::ts('Existing contacts will be matched by email address. If no match is found a new contact will be created.') .
      '<br/>' .
      E::ts('Make sure that your CSV/spreadsheet has headers in the first line') . '<br/>' .
      E::ts(
        "Optional: You may select an Activity Type or to tag contacts.
        If an Activity Type is selected, activities will be created for each contact.
        If the tag option is selected, tags will automatically be created (if they do not already exist) based on the 'source' field and added to the contact record."
      )
    );
  }

  /**
   * Check if we have all the data we need.
   */
  function mapfieldsPostProcessPre(&$form) {
    // We don't have any required fields.
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

    $contactGetParams = [
      'return' => ['email', 'first_name', 'last_name'],
      'sequential' => 1,
    ];

    switch ($fields['contact_match']) {
      case 'email':
        $createIfNotFound = TRUE;
        $contactGetParams['email'] = $params['email'];
        break;

      case 'display_name':
        $createIfNotFound = FALSE;
        $contactGetParams['display_name'] = $params['first_name'];
        break;
    }

    $contacts = civicrm_api3('Contact', 'get', $contactGetParams)['values'];
    $contact_id = $contacts[0]['id'] ?? NULL;

    if ($contact_id && ($fields['contact_match'] !== 'display_name')) {
      // If the contact has a name, make sure the firstname matches
      // Otherwire we create a duplicate and let later deduping handle it.
      if (!empty($contacts[0]['first_name']) && !empty($params['first_name'])) {
        $a = mb_strtolower($contacts[0]['first_name']);
        $b = mb_strtolower($params['first_name']);

        if ($a != $b) {
          $contact_id = NULL;
        }
      }
      elseif (empty($contacts[0]['first_name']) && empty($contacts[0]['last_name']) && !empty($params['first_name'])
        && !empty($createIfNotFound)) {
        // Assume it's OK to update the contact name, since it was empty in CiviCRM
        civicrm_api3('Contact', 'create', [
          'contact_id' => $contact_id,
          'first_name' => $params['first_name'],
          'last_name' => $params['last_name'],
        ]);
      }
    }

    if (empty($contact_id) && empty($createIfNotFound)) {
      CRM_Advimport_Utils::logImportMessage($params, E::ts('Contact not found so not imported.'));
      return;
    }

    if (empty($contact_id)) {
      $contact = civicrm_api3('Contact', 'create', [
        'contact_type' => 'Individual',
        'first_name' => $params['first_name'],
        'last_name' => $params['last_name'],
        'email' => $params['email'],
      ]);

      $contact_id = $contact['id'];
    }

    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_contact', $contact_id);

    $location_type_id = CRM_Advimport_Utils::getDefaultLocationType();

    if (!empty($params['phone'])) {
      CRM_Advimport_Utils::updateContactRelatedEntity('Phone', [
        'location_type_id' => $location_type_id,
        'contact_id' => $contact_id,
        'phone' => $params['phone'],
      ]);
    }

    if (!empty($params['country'])) {
      $address = [
        'contact_id' => $contact_id,
        'location_type_id' => $location_type_id,
      ];

      $address['country_id'] = CRM_Advimport_Utils::getCountryID($params['country'], [
        'column' => 'iso_code',
      ]);

      $fields = ['street_address_1', 'street_address_2', 'city', 'zip_code'];

      foreach ($fields as $f) {
        if (!empty($params[$f])) {
          $address[$f] = $params[$f];
        }
      }

      $stateProvinceID = CRM_Advimport_Utils::getStateProvinceID($params['state'], [
        'column' => 'abbreviation',
        'country_id' => $address['country_id'],
      ]);

      if ($stateProvinceID) {
        $address['state_province_id'] = $stateProvinceID;
      }

      CRM_Advimport_Utils::updateContactRelatedEntity('Address', $address);
    }

    if (!empty($fields['activity_type_id'])) {
      $activityParams = [
        'activity_type_id' => $fields['activity_type_id'],
        'subject' => $params['source'],
        'target_contact_id' => $contact_id,
        'status_id' => 'Completed',
      ];
      if (!empty($params['activity_date'])) {
        $activityParams['activity_date_time'] = date('YmdHis', strtotime($params['activity_date']));
      }
      civicrm_api3('Activity', 'create', $activityParams);
    }

    if (!empty($fields['tag_contact']) && !empty($params['source'])) {
      CRM_Advimport_Utils::addContactToTagByName($contact_id, $params['source']);
    }

    CRM_Advimport_Utils::addContactToGroupOrTag($contact_id, $params);
  }

}
