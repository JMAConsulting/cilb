<?php

/**
 * Advimport.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_delete($params) {
  $tableName = CRM_Core_DAO::singleValueQuery('SELECT table_name FROM civicrm_advimport WHERE id = %1', [
    1 => [$params['id'], 'Positive'],
  ]);

  if ($tableName) {
    $transaction = new CRM_Core_Transaction();
    CRM_Core_DAO::executeQuery("DROP TABLE $tableName");
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_advimport WHERE id = %1', [1 => [$params['id'], 'Positive']]);
    $transaction->commit();
  }

  return civicrm_api3_create_success(1, $params, 'AdvImport', 'delete');
}
