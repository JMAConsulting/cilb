<?php

/**
 * AdvimportRow.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_row_create($params) {
  $result = [
    'values' => [],
  ];

  [$advimport_id, $row_id] = explode('-', $params['id']);

  // This also acts as a permission check
  $import = \Civi\Api4\Advimport::get()
    ->addWhere('id', '=', $advimport_id)
    ->execute()
    ->first();

  if (empty($import)) {
    throw new Exception("Failed to fetch info about import ID: $advimport_id");
  }

  $advimport_table = $import['table_name'];

  if (empty($advimport_table)) {
    throw new Exception(ts("Advimport table not found: %1", [1 => $advimport_id]));
  }

  // page and noheader are for WordPress
  $ignore_params = ['id', 'version', 'check_permissions', 'undefined', 'page', 'noheader'];

  if ($row_id) {
    foreach ($params as $key => $val) {
      if (in_array($key, $ignore_params)) {
        continue;
      }

      CRM_Core_DAO::executeQuery('UPDATE %1 SET %2 = %3 where `row`= %4', [
        1 => [$advimport_table, 'MysqlColumnNameOrAlias'],
        2 => [$key, 'MysqlColumnNameOrAlias'],
        3 => [$val, 'String'],
        4 => [$row_id, 'Positive'],
      ]);
    }
  }
  else {
    $sql_insert = 'INSERT INTO `' . $advimport_table . ' (';
    $sql_fields = [];
    $sql_values = [];
    $sql_params = [];
    $count_params = 1;

    foreach ($params as $key => $val) {
      if (in_array($key, $ignore_params)) {
        continue;
      }
      if (preg_match('/^dp[0-9]+$/', $key)) {
        // Date Picker
        continue;
      }

      $sql_fields[$count_params] = $key;
      $sql_values[$count_params] = '%' . $count_params;
      $sql_params[$count_params] = [$val, 'String'];
      $count_params++;
    }

    if (!empty($sql_params)) {
      CRM_Core_DAO::executeQuery('INSERT INTO `' . $advimport_table . '` (' . implode(',', $sql_fields) . ') VALUES (' . implode(',', $sql_values) . ')', $sql_params);
    }
  }

  return $result;
}
