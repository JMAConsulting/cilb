<?php

function _civicrm_api3_advimport_row_bulkupdate_spec(&$params) {
  $params['id']['api.required'] = 1;
  $params['field']['api.required'] = 1;
  $params['value']['api.required'] = 1;
}

/**
 * AdvimportRow.bulkupdate API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_row_bulkupdate($params) {
  $result = [
    'values' => [],
  ];

  $id = $params['id'];

  // This also acts as a permission check
  $import = \Civi\Api4\Advimport::get()
    ->addWhere('id', '=', $id)
    ->execute()
    ->first();

  if (empty($import)) {
    throw new Exception("Failed to fetch info about import ID: $id");
  }

  // Set the mapper helper class
  $classname = $import['classname'];
  $helper = new $classname();
  $fields = $helper->getMapping();

  // @todo Add support for helpers that support user mappings
  // i.e. check $import['mapping']

  $count = 0;

  if (!empty($fields[$params['field']]['bulk_update'])) {
    // Count the rows that will be updated
    // Forcing utf8mb4_bin because otherwise we cannot fix accents or case sensitivity
    $count = CRM_Core_DAO::singleValueQuery('SELECT count(*) FROM %1 WHERE %2 <> %3 COLLATE utf8mb4_bin', [
      1 => [$import['table_name'], 'MysqlColumnNameOrAlias'],
      2 => [$params['field'], 'MysqlColumnNameOrAlias'],
      3 => [$params['value'], 'String'],
    ]);

    CRM_Core_DAO::executeQuery('UPDATE %1 SET %2 = %3', [
      1 => [$import['table_name'], 'MysqlColumnNameOrAlias'],
      2 => [$params['field'], 'MysqlColumnNameOrAlias'],
      3 => [$params['value'], 'String'],
    ]);
  }
  else {
    throw new Exception('Cannot bulk update this field:' . print_r($fields[$params['field']], 1));
  }

  return [
    'is_error' => 0,
    'values' => [],
    'count' => $count,
  ];
}
