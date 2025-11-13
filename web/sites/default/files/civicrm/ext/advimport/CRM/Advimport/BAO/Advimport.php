<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_BAO_Advimport extends CRM_Advimport_DAO_Advimport {

  /**
   * Return an array of all available types of imports possible.
   *
   * Extensions can implement their own helper by implementing
   * hook_civicrm_advimport_helpers().
   */
  public static function getHelpers() {
    $helpers = [];
    CRM_Advimport_Utils_Hook::getAdvimportHelpers($helpers);

    foreach ($helpers as $key => $val) {
      if (!empty($val['hidden'])) {
        unset($helpers[$key]);
      }
    }

    // Sort by mapping label
    usort($helpers, fn($a, $b) => strcmp($a['label'], $b['label']));

    return $helpers;
  }

  /**
   * Creates the group or tag associated with the entities imported.
   */
  public static function createGroupOrTag($type, $label) {
    // Title can be max 64 chars
    $label = substr($label, 0, 64);

    // If we've clicked "back" then we may already have a tag/group
    //  so we try and retrieve it before creating and use it again if it exists
    if ($type == 'tag') {
      try {
        $result = civicrm_api3('Tag', 'getsingle', [
          'name' => $label,
        ]);
      }
      catch (CiviCRM_API3_Exception $e) {
        $result = civicrm_api3('Tag', 'create', [
          'name' => $label,
        ]);
      }
      return $result['id'];
    }

    if ($type == 'group') {
      try {
        $result = civicrm_api3('Group', 'getsingle', [
          'title' => $label,
        ]);
      }
      catch (CiviCRM_API3_Exception $e) {
        $result = civicrm_api3('Group', 'create', [
          'title' => $label,
        ]);
      }
      return $result['id'];
    }

    throw new Exception('Unknown type: must be group or tag');
  }

  /**
   * Load all the items from the staging temp SQL table and into a CiviCRM queue.
   */
  public static function processAllItems($params) {
    $required = ['advimport_id', 'helper', 'queue'];
    foreach ($required as $req) {
      if (empty($params[$req])) {
        throw new Exception('Required missing param: ' . $req);
      }
    }

    $advimport_id = $params['advimport_id'];
    $helper = $params['helper'];
    $queue = $params['queue'];

    // For the 'add to group/tag'
    $group_or_tag = $params['group_or_tag'] ?? NULL;
    $group_or_tag_id = $params['group_or_tag_id'] ?? NULL;

    $import_table_name = CRM_Core_DAO::singleValueQuery('SELECT table_name FROM civicrm_advimport WHERE id = %1', [
      1 => [$advimport_id, 'Positive'],
    ]);

    $batch_max = 50;
    $batch = [];

    // Shown in the queue status
    $count = 0;
    $task_name = $helper->getHelperLabel();

    // For each row of the uploaded document,
    // - validate basic mandatory fields and catch easy errors (the API may catch more later).
    // - do an API call to create the data (activity or contact).
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM $import_table_name WHERE import_status = 0");

    while ($dao->fetch()) {
      $params = [];
      $params['advimport_id'] = $advimport_id;
      $params['group_or_tag'] = $group_or_tag;
      $params['group_or_tag_id'] = $group_or_tag_id;
      $params['import_table_name'] = $import_table_name;
      $params['import_row_id'] = $dao->row;

      $batch[] = $params;
      $count++;

      if ($count % $batch_max == 0) {
        $queue->createItem(new CRM_Queue_Task(
          ['CRM_Advimport_Upload_Form_MapFields', 'processItem'],
          [$batch],
          E::ts("Task %1: %2 [%3 items]", [1 => $task_name, 2 => $count, count($batch)])
        ));

        $batch = [];
      }
    }

    // Add the last items to the queue
    if (!empty($batch)) {
      $queue->createItem(new CRM_Queue_Task(
        ['CRM_Advimport_Upload_Form_MapFields', 'processItem'],
        [$batch],
        E::ts("Task %1: %2 [%3 items]", [1 => $task_name, 2 => $count, count($batch)])
      ));
    }

    // If the helper supports it, add the post-import task
    if (method_exists($helper, 'postImport')) {
      $params = [];
      $params['advimport_id'] = $advimport_id;

      $queue->createItem(new CRM_Queue_Task(
        ['CRM_Advimport_Upload_Form_MapFields', 'postImport'],
        [$params],
        E::ts("Task %1: post-import", [1 => $task_name])
      ));
    }

    $dao->free();
    return $count;
  }

  /**
   * Calcualtes a few stats on the import and saves to the advimport table so that we
   * can more quickly view the stats on the main Advimport page.
   */
  public static function updateStats($advimport_id, $update = TRUE) {
    $table_name = CRM_Core_DAO::singleValueQuery('SELECT table_name FROM civicrm_advimport WHERE id = %1', [
      1 => [$advimport_id, 'Positive'],
    ]);

    $sqlparams = [
      1 => [$table_name, 'MysqlColumnNameOrAlias'],
    ];

    $stats = [];
    $stats['total_count'] = CRM_Core_DAO::singleValueQuery('SELECT count(*) FROM %1', $sqlparams);

    if (preg_match('/^civicrm_advimport_/', $table_name)) {
      $stats['success_count'] = CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM %1 WHERE import_status = 1', $sqlparams);
      $stats['error_count'] = CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM %1 WHERE import_status = 2', $sqlparams);
      $stats['warning_count'] = CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM %1 WHERE import_status = 3', $sqlparams);
    }
    elseif (preg_match('/^civicrm_tmp_d/', $table_name)) {
      // Core imports can be: 'IMPORTED', 'ERROR', 'DUPLICATE', 'INVALID', 'NEW' (not yet imported)
      $stats['success_count'] = CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM %1 WHERE _status = "IMPORTED"', $sqlparams);
      $stats['error_count'] = CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM %1 WHERE _status = "ERROR" OR _status = "INVALID"', $sqlparams);
      $stats['warning_count'] = CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM %1 WHERE _status = "DUPLICATE"', $sqlparams);
    }

    if ($update) {
      CRM_Core_DAO::executeQuery('UPDATE civicrm_advimport
        SET end_date = NOW(),
            total_count = %2,
            success_count = %3,
            warning_count = %4,
            error_count = %5
        WHERE id = %1', [
        1 => [$advimport_id, 'Positive'],
        2 => [$stats['total_count'], 'Integer'],
        3 => [$stats['success_count'], 'Integer'],
        4 => [$stats['warning_count'], 'Integer'],
        5 => [$stats['error_count'], 'Integer'],
      ]);
    }

    return $stats;
  }

  /**
   * Re-enable DB Logging
   *
   * @see CRM_Logging_Schema::disableLoggingForThisConnection()
   */
  public static function reEnableLogging() {
    if (CRM_Core_Config::singleton()->logging) {
      CRM_Core_DAO::executeQuery('SET @civicrm_disable_logging = 0');
    }
  }

  /**
   * Extracts the headers/columns from the data and creates a new SQL table,
   * then stores the data in it.
   * @param $headers
   * @param $data
   *
   * @returns string|null $tableName
   */
  public static function saveToDatabaseTable(&$headers, &$data, &$controller = NULL) {
    $dao = new CRM_Core_DAO();
    $db = $dao->getDatabaseConnection();

    CRM_Logging_Schema::disableLoggingForThisConnection();

    $tableName = 'civicrm_advimport_' . date('Ymd_His');

    if ($controller) {
      $controller->set('tableName', $tableName);
      $controller->set('headers', $headers);
    }

    // headers might be empty if creating a LookupTable
    if ($controller && empty($headers)) {
      CRM_Core_Session::setStatus(E::ts("There was a problem analysing the uploaded file, or it was empty. Please verify the file and try again."));
      $controller->resetPage('DataUpload');
      return NULL;
    }

    //
    // Create the temp table (it's not really temp, we will need to delete it when finished).
    //
    $col_headers = [];
    $columns = [];

    $columns[] = "`row` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique row ID'";
    $columns[] = "`import_status` tinyint(4) unsigned NOT NULL DEFAULT 0 COMMENT 'Import status: 0 = not imported, 1 = OK, 2 = Error, 3 = Warning'";
    $columns[] = "`import_error` TEXT DEFAULT NULL COMMENT 'Import error message'";
    $columns[] = "`entity_table` VARCHAR(64) DEFAULT NULL COMMENT 'CiviCRM corresponding Entity Table'";
    $columns[] = "`entity_id` int(10) unsigned DEFAULT NULL COMMENT 'CiviCRM corresponding Entity ID'";

    foreach ($headers as $key => $val) {
      $val = CRM_Advimport_Utils::convertToMachineName($val);

      if (in_array($val, ['entity_table', 'entity_id', 'row', 'import_status', 'import_error'])) {
        // We still allow passing these column headers to pre-fill data, such as for LookupTableContact
        $col_headers[$key] = $val;
        continue;
      }

      $sql_field = '`' . $val . '`';

      // Avoid duplicate SQL columns, which will cause confusing fatal errors
      if (in_array($sql_field, $col_headers)) {
        throw new Exception("Duplicate column names ({$val}). Please make sure that column headers are unique.");
      }

      $columns[] = $sql_field . ' TEXT DEFAULT NULL';
      $col_headers[$key] = $sql_field;
    }

    $columns[] = 'PRIMARY KEY (`row`)';

    $createSql = implode(', ', $columns);

    $db->query("DROP TABLE IF EXISTS $tableName");
    $db->query("CREATE TABLE $tableName ( $createSql ) ENGINE=InnoDB");

    // Insert the data (except the first row, it has headers).
    // NB: for now, we're assuming that all data are string (which, of course, is not true.. but easier for now).
    $base_sql = 'INSERT INTO `' . $tableName . '` (' . implode(',', $col_headers) . ') VALUES';

    foreach ($data as $val) {
      $cols = [];
      $params = [];
      $cpt = 0;

      // Sometimes phpexcel can return us some empty rows.
      // This will be true if we found some valid data in a cell.
      $data_found = FALSE;

      foreach ($headers as $vv) {
        $cols[] = '%' . $cpt;

        // NULL values cause the String validation to do a fatal exception.
        if (isset($val[$vv])) {
          $params[$cpt] = [$val[$vv], 'String'];
          $data_found = TRUE;
        }
        else {
          $params[$cpt] = ['', 'String'];
        }

        $cpt++;
      }

      if ($data_found) {
        CRM_Core_DAO::executeQuery($base_sql . '(' . implode(', ', $cols) . ')', $params);
      }
    }

    CRM_Advimport_BAO_Advimport::reEnableLogging();

    return $tableName;
  }

}
