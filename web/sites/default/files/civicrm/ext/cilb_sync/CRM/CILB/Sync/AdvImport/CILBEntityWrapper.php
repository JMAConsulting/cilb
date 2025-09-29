<?php

use League\Csv\Reader;

use CRM_CILB_Sync_ExtensionUtil as E;
use CRM_CILB_Sync_Utils as EU;

use Civi\Api4\CustomValue;

class CRM_CILB_Sync_AdvImport_CILBEntityWrapper extends CRM_Advimport_Helper_Csv {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("CILB Exam Entities");
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
    $delimiter = ",";
    $headers = [0 => 'External Identifier', 1 => 'Entity ID', 2 => 'Class Code'];

    // cannot use CSV Helper as we need to add missing headers
    $csv = Reader::createFromPath($file);
    $csv->setDelimiter($delimiter);
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

    $externalID = $params['external_identifier'] ?? NULL;
    $candidateID = $params['entity_id'] ?? NULL;
    $classCode = $params['class_code'] ?? NULL;

    // Sanity Checks
    if (empty($externalID)) {
      throw new CRM_Core_Exception("uploaded file is missing the External Identifier.");
    }
    if (empty($candidateID)) {
      throw new CRM_Core_Exception("uploaded file is missing the Entity ID.");
    }
    if (empty($classCode)) {
      throw new CRM_Core_Exception("uploaded file is missing the class code information.");
    }

    // Contact ID + Existing Candidate Info
    $contact = EU::getCandidateEntityFromExternalID($externalID, $candidateID, $classCode);
    if ($contact == NULL) {
      throw new CRM_Core_Exception('Cannot Identify Contact From External Identifier');
    }
    $contactID = $contact['id'];

    // If candidate info exists already, skip
    if ( !empty($contact['custom_cilb_candidate_entity.id']) ) {
      CRM_Advimport_Utils::logImportWarning($params, "Found Candidate information in database already");
      return;
    }

    // Exam Category
    $examCategory = EU::getExamInfoFromClassCode($classCode);
    if ($examCategory == NULL) {
      throw new CRM_Core_Exception('Cannot determine Exam Category from class_code ' . $classCode);
    }

    // Add Candidate Info
    try {
      $result = CustomValue::create('cilb_candidate_entity', FALSE)
        ->addValue('entity_id', $contactID)
        ->addValue('Entity_ID_imported_', $candidateID)
        ->addValue('class_code', $classCode)
        ->addValue('exam_category', $examCategory['value'])
        ->execute();

      if (!empty($result['error_message'])) {
        \CRM_Core_Error::debug_var('custom_value_create_error', $result);
        throw new \CRM_Core_Exception("Failed to update candidate entity.");
      }
    }
    catch (\CRM_Core_Exception $e) {
      \CRM_Core_Error::debug_var('custom_value_create_error', $e->getMessage());
      throw new \CRM_Core_exception("Failed to update candidate entity.");
    }

    // Succesfully updated.
    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_contact', $contactID);

  }

}