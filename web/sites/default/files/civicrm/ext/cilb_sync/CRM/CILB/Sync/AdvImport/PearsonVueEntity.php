<?php

use CRM_CILB_Sync_ExtensionUtil as E;
use CRM_CILB_SYNC_Utils as U;

class CRM_CILB_Sync_AdvImport_PearsonEntity extends CRM_Advimport_Helper_Csv {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("PearsonVue Exam Entities");
  }

  /**
   * By default, a field mapping will be shown, but unless you have defined
   * one in getMapping() - example later below - you may want to skip it.
   * Displaying it is useful for debugging at first.
   */
  public function mapfieldMethod() {
    return 'skip';
  }

  /**
   * Returns the data from the file.
   * Supports CSV, Excel and ODS (whatever PHPExcel supports).
   *
   * @param $file
   * @param $delimiter
   * @param $encoding
   *
   * @returns Array
   */
  function getDataFromFile($file, $delimiter = '', $encoding = 'UTF-8') {
    // Use the fieldSeparator by default, it defaults to ','
    if (empty($delimiter)) {
      $delimiter = Civi::settings()->get('fieldSeparator');
    }

    $csv = Reader::createFromPath($file);
    $csv->setDelimiter($delimiter);


    $headers = [0 => 'External Identifier', 1 => 'Entity ID', 2 => 'Class Code'];
    $records = $csv->getRecords();

    // Re-key data using the headers
    $data = [];

    foreach ($records as $val) {
      $i = 0;

      foreach ($val as $kk => $vv) {
        if (!isset($headers[$kk])) {
          unset($headers[$kk]);
          // Skip empty headers
          continue;
        }
        $d[$headers[$kk]] = $vv;
        $i++;
      }

      // Remove fields we do not want to re-import
      // Has to be done after, so that the order of colums is respected
      unset($d['import_status']);
      unset($d['import_error']);

      $data[] = $d;
    }

    return [$headers, $data];
  }



  /**
   * Import an item gotten from the queue.
   *
   * This is where, in custom PHP import scripts, you would program all
   * the logic on how to handle imports the old fashioned way.
   */
  public function processItem($params) {

    $row_id = $params['import_row_id'];
    $table_name = $params['import_table_name'];

    $external_identifer = $params['external_identifier'] ?? NULL;
    $entity_id = $params['entity_id'] ?? NULL;
    $class_code = $params['class_code'] ?? NULL;

    // Sanity Checks
    if (empty($external_identifer)) {
      throw new CRM_Core_Exception("uploaded file is missing the External Identifier.");
    }
    if (empty($entity_id)) {
      throw new CRM_Core_Exception("uploaded file is missing the Entity ID.");
    }
    if (empty($class_code)) {
      throw new CRM_Core_Exception("uploaded file is missing the class code information.");
    }

    $contact = U::getCandidateContactIDFromExternalIdentifier($external_identifer);
    if ($contact->count() == 0) {
      throw new CRM_Core_Exception('Cannot Identify Contact From External Identifier');
    }
    $contactID = $contact[0]['id'];
    $candidate = U::getCandidateEntity($entity_id, $class_code);
    if ($candidate) {
      CRM_Advimport_Utils::logImportWarning($params, "Skipped");
    }
    $examCategory = U::getExamCategoryFromClassCode($class_code);
    if (empty($examCategory)) {
      throw new CRM_Core_Exception('Cannot determine Exam Category from class_code ' . $class_code);
    }
    \Civi\Api4\CustomValue::create('cilb_candidate_entity', FALSE)
      ->addValue('entity_id', $contactID)
      ->addValue('Entity_ID_imported_', $entity_id)
      ->addValue('class_code', $class_code)
      ->addValue('exam_category', $examCategory['value'])
      ->execute();

    // Succesfully updated.
    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_contact', $contactID);
  }

}