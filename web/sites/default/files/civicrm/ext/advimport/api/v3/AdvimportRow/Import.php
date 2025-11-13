<?php

/**
 * AdvimportRow.import API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_row_import($params) {
  $result = [
    'values' => [],
    'is_error' => 0,
  ];

  // We should separate params?
  [$advimport_id, $row_id] = explode('-', $params['id']);

  // This also acts as a permission check
  $import = \Civi\Api4\Advimport::get()
    ->addWhere('id', '=', $advimport_id)
    ->execute()
    ->first();

  if (empty($import)) {
    throw new Exception("Failed to fetch info about import ID: $advimport_id");
  }

  // FIXME FIXME: code below is extremely redundant with CRM/Advimport/Upload/Form/MapFields.php
  $mapping = NULL;
  $helper = NULL;

  $mapping = $import['mapping'];
  $classname = $import['classname'];
  $advimport_table = $import['table_name'];

  if (!empty($mapping)) {
    $mapping = json_decode($mapping, TRUE);
  }

  // Set the mapper helper class
  $helper = new $classname();

  // Clear warnings/errors
  CRM_Core_DAO::executeQuery('UPDATE %1 SET import_error = NULL, import_status = 0 where `row`= %2', [
    1 => [$advimport_table, 'MysqlColumnNameOrAlias'],
    2 => [$row_id, 'Positive'],
  ]);

  $dao = CRM_Core_DAO::executeQuery('SELECT * FROM %1 where `row`= %2', [
    1 => [$advimport_table, 'MysqlColumnNameOrAlias'],
    2 => [$row_id, 'Positive'],
  ]);

  $dao->fetch();

  $data = CRM_Advimport_Upload_Form_MapFields::convertDaoToArray($dao);
  $params = []; // FIXME Is some data missing? group/tag settings?

  $params['import_row_id'] = $row_id;
  $params['import_table_name'] = $advimport_table;
  $params['group_or_tag'] = $import['track_entity_type'];
  $params['group_or_tag_id'] = $import['track_entity_id'];

  $params += $data;

  // Data is stored in the tmp table with their original column mames
  // Here is where we remap those fields to the new field names.
  if (!empty($mapping)) {
    foreach ($params as $key => $val) {
      // QuickForm replaces the spaces by underscores when it generates the mapfields form
      // so the mapping saved in DB will have underscores.
      // FIXME: This might not be necessary anymore, since we convert to machine key.
      $key = CRM_Advimport_Utils::convertToMachineName($key);

      if (isset($mapping[$key])) {
        $new_key = $mapping[$key];

        // If the CSV column key is the same as the mapping key
        // we want to avoid doing an 'unset' on the params :-)
        if ($new_key != $key) {
          $params[$new_key] = $params[$key];
          unset($params[$key]);
        }
      }
    }
  }

  // NB: this might throw an exception, but depending on the errorMode
  // provided to the Queue Runner, it will either prompt or ignore.
  try {
    $helper->processItem($params);

    // Do not change the import_status=1 if processItem() generated warnings (import_status=3)
    CRM_Logging_Schema::disableLoggingForThisConnection();
    CRM_Core_DAO::executeQuery('UPDATE %1 SET import_status = 1 where `row`= %2 and import_status <> 3', [
      1 => [$params['import_table_name'], 'MysqlColumnNameOrAlias'],
      2 => [$params['import_row_id'], 'Positive'],
    ]);
    CRM_Advimport_BAO_Advimport::reEnableLogging();

    $dao = CRM_Core_DAO::executeQuery('SELECT import_status, import_error, entity_table, entity_id
      FROM %1
      where `row`= %2', [
      1 => [$params['import_table_name'], 'MysqlColumnNameOrAlias'],
      2 => [$row_id, 'Positive'],
    ]);

    if ($dao->fetch()) {
      $result['messages'] = json_decode($dao->import_error, TRUE);
      $result['status'] = $dao->import_status;
      $result['entity_table'] = $dao->entity_table;
      $result['entity_id'] = $dao->entity_id;
    }
  }
  catch (Exception $e) {
    // Log and throw back for errorMode handling.
    Civi::log()->warning('Import: ' . $e->getMessage() . ' --- ' . print_r($params, 1) . ' -- ' . CRM_Core_Error::formatBacktrace($e->getTrace()));

    CRM_Logging_Schema::disableLoggingForThisConnection();
    CRM_Core_DAO::executeQuery('UPDATE %1 SET import_status = 2, import_error = %3 where `row`= %2', [
      1 => [$advimport_table, 'MysqlColumnNameOrAlias'],
      2 => [$row_id, 'Positive'],
      3 => [$e->getMessage(), 'String'],
    ]);
    CRM_Advimport_BAO_Advimport::reEnableLogging();

    $result['is_error'] = 1;
    $result['error_message'] = $e->getMessage();
  }

  CRM_Advimport_BAO_Advimport::updateStats($advimport_id);

  return $result;
}
