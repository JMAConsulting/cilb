<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Job.advimportcleanup API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_job_advimportcleanup_spec(&$spec) {
}

/**
 * Job.advimportcleanup API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_advimportcleanup($params) {
  $output = null;
  $result = [];

  if (empty($params['days'])) {
    throw new Exception(E::ts('You must provide a days=123 parameters. Imports older than this number of days will be deleted.'));
  }

  if (!is_numeric($params['days'])) {
    throw new Exception(E::ts('The days=123 parameter is not numeric.'));
  }

  $date = new DateTime();
  $date->modify('-' . $params['days'] . ' day');
  $isodate = $date->format("Y-m-d");

  // This makes it possible to override whether to use the start_date or end_date
  $date_field = $params['date_field'] ?? 'end_date';

  $api = \Civi\Api4\Advimport::get(FALSE)
    ->addWhere('table_name', 'IS NOT NULL')
    ->addWhere($date_field, '<', $isodate);

  if (!empty($params['classname'])) {
    $classes = explode(',', $params['classname']);
    $api->addWhere('classname', 'IN', $classes);
  }

  $advimports = $api->execute();

  foreach ($advimports as $advimport) {
    if (!preg_match('/^civicrm_advimport_/', $advimport['table_name'])) {
      $output .= "Skipping because it does not seem like an advimport table: " . $advimport['table_name'] . '<br>';
      continue;
    }

    $output .= 'Delete: ' . $advimport['id'] . '<br>';

    try {
      CRM_Core_DAO::executeQuery('DROP TABLE %1', [
        1 => [$advimport['table_name'], 'MysqlColumnNameOrAlias'],
      ]);
    }
    catch (Exception $e) {
      $output .= "Failed to drop table {$advimport['table_name']}: " . $e->getMessage() . '<br>';
    }

    // Can setting to NULL be done with api4?
    CRM_Core_DAO::executeQuery('UPDATE civicrm_advimport SET table_name = NULL WHERE id = %1', [
      1 => [$advimport['id'], 'Positive'],
    ]);
  }

  return civicrm_api3_create_success($output);
}
