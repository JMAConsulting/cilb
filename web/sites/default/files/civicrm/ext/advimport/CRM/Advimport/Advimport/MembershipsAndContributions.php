<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_MembershipsAndContributions extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("Memberships and Contributions");
  }

  /**
   * Available fields.
   */
  function getMapping(&$form) {
    return [
      'contact_id' => [
        'label' => E::ts('Contact ID'),
        'field' => 'contact_id',
      ],
      'external_identifier' => [
        'label' => E::ts('External ID'),
        'field' => 'external_identifier',
      ],
      'first_name' => [
        'label' => E::ts('First Name'),
        'field' => 'first_name',
      ],
      'last_name' => [
        'label' => E::ts('Last Name'),
        'field' => 'last_name',
      ],
      'organization_name' => [
        'label' => E::ts('Organization Name'),
        'field' => 'organization_name',
      ],
      'email' => [
        'label' => E::ts('Email'),
        'field' => 'email',
      ],
      'membership_type_id' => [
        'label' => E::ts('Membership Type'),
        'field' => 'membership_type_id',
        'bulk_update' => true,
      ],
      'start_date' => [
        'label' => E::ts('Membership Start Date'),
        'field' => 'start_date',
        'bulk_update' => true,
      ],
      'join_date' => [
        'label' => E::ts('Membership Join Date'),
        'field' => 'join_date',
        'bulk_update' => true,
      ],
      'contribution_source' => [
        'label' => E::ts('Contribution Source'),
        'field' => 'contribution_source',
        'bulk_update' => true,
      ],
      'receive_date' => [
        'label' => E::ts('Contribution Receive Date'),
        'field' => 'receive_date',
        'bulk_update' => true,
      ],
      'financial_type_id' => [
        'label' => E::ts('Contribution Financial Type'),
        'field' => 'financial_type_id',
        'bulk_update' => true,
      ],
      'total_amount' => [
        'label' => E::ts('Contribution Total Amount'),
        'field' => 'total_amount',
        'bulk_update' => true,
      ],
      'trxn_id' => [
        'label' => E::ts('Transaction Number'),
        'field' => 'trxn_id',
      ],
      'invoice_id' => [
        'label' => E::ts('Invoice ID'),
        'field' => 'invoice_id',
      ],
      'invoice_number' => [
        'label' => E::ts('Invoice Number'),
        'field' => 'invoice_number',
      ],
      'payment_instrument_id' => [
        'label' => E::ts('Payment Method'),
        'field' => 'payment_instrument_id',
        'bulk_update' => true,
      ],
      'contribution_status_id' => [
        'label' => E::ts('Contribution Status'),
        'field' => 'contribution_status_id',
        'bulk_update' => true,
      ],
    ];
  }

  /**
   * Import an item gotten from the queue.
   */
  function processItem($params) {
    $contact = [];
    // Only deduping by email for now
    $contact_id = null;

    if (!empty($params['contact_id'])) {
      $contact_id = $params['contact_id'];
    }

    if (!$contact_id && !empty($params['external_identifier'])) {
      $contact = \Civi\Api4\Contact::get(false)
        ->addSelect('id')
        ->addWhere('external_identifier', '=', $params['external_identifier'])
        ->execute()
        ->first();

      $contact_id = $contact['id'] ?? NULL;
    }

    // @todo This has not been tested much
    if (!$contact_id && !empty($params['email'])) {
      $emails = \Civi\Api4\Email::get(false)
        ->addSelect('contact_id')
        ->addWhere('email', '=', $params['email'])
        ->execute();

      if ($emails->count() == 1) {
        $email = $emails->first();
        $contact_id = $email['contact_id'];
      }
    }

    // @todo search by first/last_name
    // @todo search by organization_name
    // @todo Create the contact, if none found and we have enough data


    if (!$contact_id) {
      throw new Exception('Contact not found');
    }

    $params['contact_id'] = $contact_id;

    // Mandatory fields
    $mandatory_fields = ['start_date', 'total_amount'];
    foreach ($mandatory_fields as $f) {
      if (empty($params[$f])) {
        throw new Exception('Missing: ' . $f);
      }
    }

    // Fix Excel dates
    $fix_date_fields = ['join_date', 'start_date'];
    foreach ($fix_date_fields as $f) {
      if (!empty($params[$f]) && is_numeric($params[$f])) {
        $params[$f] = $this->excelDateToISO($params[$f]);
      }
    }

    // Lookup the some IDs, if present (when in non-English, it can be unpredictable)
    $lookup = ['contribution_status_id', 'payment_instrument_id'];

    foreach ($lookup as $key) {
      if (!empty($params[$key]) && !is_numeric($params[$key])) {
        $options = civicrm_api3('Contribution', 'getoptions', [
          'field' => $key,
        ])['values'];

        $find = array_search($params[$key], $options);

        if ($find !== false) {
          $params[$key] = $find;
        }
      }
    }

    // This is used both by the Membership and Contribution (because the
    // Membership.create might create the Contribution)
    if (empty($params['receive_date'])) {
      $params['receive_date'] = $params['start_date'];
    }

    if (empty($params['financial_type_id'])) {
      // Try to guess - this might not be very wise
      $financial_type_id = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_financial_type WHERE name = %1', [
        1 => [E::ts('Membership'), 'String'],
      ]);

      if (!$financial_type_id) {
        $financial_type_id = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_financial_type WHERE id = 2');
      }

      if ($financial_type_id) {
        $params['financial_type_id'] = $financial_type_id;
      }
    }

    // Check if the membership already exists
    $membership_id = NULL;
    $contribution_id = NULL;

    $result = civicrm_api3('Membership', 'get', [
      'contact_id' => $contact_id,
      'start_date' => $params['start_date'],
      'membership_type_id' => $params['membership_type_id'],
      'sequential' => 1,
    ]);

    if (!empty($result['values'])) {
      $membership_id = $result['values'][0]['id'];
    }
    else {
      // Create the membership
      try {
        $result = civicrm_api3('Membership', 'create', $params);
        $membership_id = $result['id'];
      }
      catch (Exception $e) {
        Civi::log()->warning('Advimport/MembershipsAndContributions: ' . print_r($e->getTrace(), 1));
        throw new Exception("Failed to create the membership: " . $e->getMessage() . ' - more details in the ConfigAndLog');
      }
    }

    if (!$membership_id) {
      throw new Exception('Unexpected error: empty membership_id');
    }

    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_contact', $contact_id);

    // Create the payment

    // Check if it already exists (@todo not very accurate)
    $result = civicrm_api3('Contribution', 'get', [
      'contact_id' => $contact_id,
      'receive_date' => $params['receive_date'],
      'total_amount' => $params['total_amount'],
      'sequential' => 1,
    ]);

    if (!empty($result['values'])) {
      $contribution_id = $result['values'][0]['id'];
    }
    else {
      try {
        $result = civicrm_api3('Contribution', 'create', $params);
        $contribution_id = $result['id'];
      }
      catch (Exception $e) {
        throw new Exception('Failed to create the contribution: ' . $e->getMessage());
      }

      try {
        $result = civicrm_api3('MembershipPayment', 'create', [
          'membership_id' => $membership_id,
          'contribution_id' => $contribution_id,
        ]);
      }
      catch (Exception $e) {
        throw new Exception('Failed to create the MembershipPayment: ' . $e->getMessage());
      }
    }

    CRM_Advimport_Utils::addContactToGroupOrTag($contact['id'], $params);
  }

}
