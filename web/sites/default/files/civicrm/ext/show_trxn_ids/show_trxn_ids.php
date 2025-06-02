<?php

require_once 'show_trxn_ids.civix.php';

use CRM_ShowTrxnIds_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function show_trxn_ids_civicrm_config(&$config): void
{
  _show_trxn_ids_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function show_trxn_ids_civicrm_install(): void
{
  _show_trxn_ids_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function show_trxn_ids_civicrm_enable(): void
{
  _show_trxn_ids_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_searchColumns/
 */
function show_trxn_ids_civicrm_searchColumns($objectName, &$headers,  &$values, &$selector): void
{
  \Civi::log()->debug($objectName);
  if ($objectName != 'contribution') return;
  foreach ($headers as $_ => $header) {
    if (isset($header['name'])) {
    }
    // \Civi::log()->debug(print_r($header['name'], TRUE) . $header['weight']);
    if (!empty($header['name']) && $header['name'] == 'Amount') {
      //NOTE: the contribution amount is hardcoded as the first column even through the weight is different - see templates/CRM/Contribute/Form/Selector.tpl:47
      // As such the trxn id is to the right of Amount
      $weight = $header['weight'] + 5;
      $headers[] = [
        'name' => E::ts('Transaction ID'),
        'field_name' => 'trxn_id',
        'type' => null,
        'weight' => $weight,
      ];
      foreach ($values as $key => $value) {
        \Civi::log()->debug(print_r($value, TRUE));
        $contribution = \Civi\Api4\Contribution::get(FALSE)
          ->addWhere('id', '=', $value['contribution_id'])
          ->addSelect('trxn_id')
          ->execute();
        if (!empty($contribution)) {
          $trxnId = $contribution[0]['trxn_id'];
        }
        $values[$key]['trxn_id'] = $trxnId;
        \Civi::log()->debug(print_r($values[$key], TRUE));
      }
      break;
    }
  }
  // foreach ($headers as $i => $header) {
  //   \Civi::log()->debug($header['name'] . $header['weight'] . print_r($header, TRUE));
  // }
}

function show_trxn_ids_civicrm_buildForm($formName, &$form)
{
  \Civi::log()->debug($formName);
  //TODO: fix the form names
  if ($formName != 'CRM_Event_Page_Tab') {
    return;
  }
  //TODO: get the actual value using the API
  $contributionId = 98;
  $trxnId = 'test';
}
