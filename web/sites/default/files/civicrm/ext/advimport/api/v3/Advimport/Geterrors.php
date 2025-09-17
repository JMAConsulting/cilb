<?php

/**
 * Advimport.geterrors API
 *
 * @todo Rename this to Advimport.getrow, or AdvimportRow.get?
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_geterrors($params) {
  $result = [
    'values' => [],
  ];

  $mysqlTableName = null;

  if (!is_numeric($params['id'])) {
    throw new Exception('advimport: invalid ID, must be numeric');
  }

  $advimport = \Civi\Api4\Advimport::get()
    ->addSelect('table_name')
    ->addWhere('id', '=', $params['id'])
    ->execute()
    ->first();

  if (empty($advimport['table_name'])) {
    throw new Exception('Import or import temp table does not exist, or access denied.');
  }

  $sql = '';
  $sql_params = [
    1 => [$advimport['table_name'], 'MysqlColumnNameOrAlias'],
  ];

  if (in_array(CRM_Utils_Array::value('status', $params), [1,2,3])) {
    // Show only warnings
    $sql = 'SELECT * FROM %1 WHERE import_status = %2';
    $sql_params[2] = [$params['status'], 'Positive'];
  }
  else {
    // Fetch everything
    $sql = 'SELECT * FROM %1 WHERE 1=1';
  }

  if (!empty($params['filter_field'])) {
    // The use of 'LIKE' is intentional, to make it easier to do partial matches,
    // especially since the import_error field, for example, has json data.
    $field = CRM_Utils_Type::escape($params['filter_field'], 'MysqlColumnNameOrAlias');
    $sql .= " AND $field LIKE %3";
    $sql_params[3] = [$params['filter_value'], 'String'];
  }

  $dao = CRM_Core_DAO::executeQuery($sql, $sql_params);

  // This is to translate core import statuses in the loop below, if it's a core import
  $map_status = [
    'NEW' => 0,
    'IMPORTED' => 1,
    'ERROR' => 2,
    'INVALID' => 2,
    'DUPLICATE' => 3,
  ];

  while ($dao->fetch()) {
    $v = $dao->toArray();

    // Warnings are json_encoded.
    // FIXME? This is not very API-like, since doing a GET/POST would alter the data.
    if ($v['import_status'] == 3) {
      $v['import_error'] = implode('; ', json_decode($v['import_error'], TRUE));
    }

    $result['values'][] = $v;
  }

  return $result;
}
