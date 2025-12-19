<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

require_once('api/v3/Payment.php');

/**
 * @todo mjwpayment.get_contribution is a replacement for Contribution.get
 *   which support querying by contribution/payment trxn_id per https://github.com/civicrm/civicrm-core/pull/14748
 *   - These API functions should be REMOVED once core has the above PR merged and we increment the min version for the extension.
 *   - The change is small, but to re-implement them here we have to copy quite a lot over.
 */
/**
 * Adjust Metadata for Get action.
 *
 * The metadata is used for setting defaults, documentation & validation.
 *
 * @param array $params
 *   Array of parameters determined by getfields.
 */
function _civicrm_api3_mjwpayment_get_contribution_spec(&$params) {
  $params = [
    'contribution_test' => [
      'api.default' => 0,
      'type' => CRM_Utils_Type::T_BOOLEAN,
      'title' => 'Get Test Contributions?',
      'api.aliases' => ['is_test'],
    ],
    'contribution_id' => [
      'title' => ts('Contribution ID'),
      'type' => CRM_Utils_Type::T_INT,
    ],
    'trxn_id' => [
      'name' => 'trxn_id',
      'type' => CRM_Utils_Type::T_STRING,
      'title' => ts('Transaction ID'),
      'description' => ts('Transaction id supplied by external processor. This may not be unique.'),
      'maxlength' => 255,
      'size' => 10,
      'where' => 'civicrm_financial_trxn.trxn_id',
      'table_name' => 'civicrm_financial_trxn',
      'entity' => 'FinancialTrxn',
      'bao' => 'CRM_Financial_DAO_FinancialTrxn',
      'localizable' => 0,
      'html' => [
        'type' => 'Text',
      ],
    ],
    'order_reference' => [
      'name' => 'order_reference',
      'type' => CRM_Utils_Type::T_STRING,
      'title' => 'Order Reference',
      'description' => 'Payment Processor external order reference',
      'maxlength' => 255,
      'size' => 25,
      'where' => 'civicrm_financial_trxn.order_reference',
      'table_name' => 'civicrm_financial_trxn',
      'entity' => 'FinancialTrxn',
      'bao' => 'CRM_Financial_DAO_FinancialTrxn',
      'localizable' => 0,
      'html' => [
        'type' => 'Text',
      ],
    ],
  ];
}

/**
 * Retrieve a set of contributions.
 *
 * @param array $params
 *  Input parameters.
 *
 * @return array
 *   Array of contribution, if error an array with an error id and error message
 *
 * @deprecated Use API4 Payment + Contribution
 */
function civicrm_api3_mjwpayment_get_contribution($params) {
  $payments = civicrm_api3('Mjwpayment', 'get_payment', $params);

  if ($payments['count'] > 0) {
    // We found at least one payment for the params we were given.
    // We may have more than one payment (eg. A payment + a refund payment)
    // Return the contribution of the FIRST payment (all found payments SHOULD reference the same contribution)
    $contributionID = reset($payments['values'])['contribution_id'];
    $contribution = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $contributionID)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute()
      ->first();
    $contribution['payments'] = $payments['values'];
  }
  else {
    $contributionApi4 = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->addOrderBy('id', 'DESC');

    if (isset($params['order_reference'])) {
      $contributionParams['trxn_id'][] = $params['order_reference'];
    }
    if (isset($params['trxn_id'])) {
      $contributionParams['trxn_id'][] = $params['trxn_id'];
    }
    if (isset($contributionParams['trxn_id'])) {
      $contributionApi4->addWhere('trxn_id', 'IN', $contributionParams['trxn_id']);
    }
    if (isset($params['contribution_id'])) {
      $contributionApi4->addWhere('id', '=', $params['contribution_id']);
    }
    $contribution = $contributionApi4->execute()->first();
  }
  $result = [];
  if ($contribution) {
    $result = [$contribution['id'] => $contribution];
  }
  return civicrm_api3_create_success($result, $params, 'Mjwpayment', 'get_contribution');
}

/**
 * Adjust Metadata for Get action.
 *
 * The metadata is used for setting defaults, documentation & validation.
 *
 * @param array $params
 *   Array of parameters determined by getfields.
 */
function _civicrm_api3_mjwpayment_get_payment_spec(&$params) {
  _civicrm_api3_payment_get_spec($params);

}

/**
 * Retrieve a set of financial transactions which are payments.
 *
 * @param array $params
 *  Input parameters.
 *
 * @return array
 *   Array of financial transactions which are payments, if error an array with an error id and error message
 * @throws \CRM_Core_Exception
 * @deprecated Use API4
 */
function civicrm_api3_mjwpayment_get_payment($params) {
  return civicrm_api3_payment_get($params);
}

/**
 * Adjust Metadata for Create action.
 *
 * The metadata is used for setting defaults, documentation & validation.
 *
 * @param array $params
 *   Array of parameters.
 */
function _civicrm_api3_mjwpayment_create_payment_spec(&$params) {
  _civicrm_api3_payment_create_spec($params);
  $customFields = \Civi\Api4\CustomField::get(FALSE)
    ->addSelect('name', 'label', 'data_type')
    ->addWhere('custom_group_id:name', '=', 'Payment_details')
    ->execute();
  foreach ($customFields as $customField) {
    unset($customField['id']);
    $customField['description'] = $customField['label'];
    $params[$customField['name']] = $customField;
  }
}

/**
 * Add a payment for a Contribution.
 * @fixme This is a copy of API3 Payment.create including some handling for bugfixes in certain versions.
 *
 * @param array $params
 *   Input parameters.
 *
 * @return array
 *   Api result array
 *
 * @throws \CRM_Core_Exception
 * @throws \CRM_Core_Exception
 * @deprecated Use API4
 */
function civicrm_api3_mjwpayment_create_payment($params) {
  if (empty($params['skipCleanMoney'])) {
    foreach (['total_amount', 'net_amount', 'fee_amount'] as $field) {
      if (isset($params[$field])) {
        $params[$field] = CRM_Utils_Rule::cleanMoney($params[$field]);
      }
    }
  }
  // Check if it is an update
  if (!empty($params['id'])) {
    $amount = $params['total_amount'];
    civicrm_api3('Payment', 'cancel', $params);
    $params['total_amount'] = $amount;
  }
  $trxn = CRM_Financial_BAO_Payment::create($params);

  $customFields = \Civi\Api4\CustomField::get(FALSE)
    ->addWhere('custom_group_id:name', '=', 'Payment_details')
    ->execute()
    ->indexBy('name');
  foreach ($customFields as $key => $value) {
    if (isset($params[$key])) {
      $customParams['custom_' . $value['id']] = $params[$key];
    }
  }
  if (!empty($customParams)) {
    $customParams['entity_id'] = $trxn->id;
    civicrm_api3('CustomValue', 'create', $customParams);
  }

  $values = [];
  _civicrm_api3_object_to_array_unique_fields($trxn, $values[$trxn->id]);
  return civicrm_api3_create_success($values, $params, 'Payment', 'create', $trxn);
}
