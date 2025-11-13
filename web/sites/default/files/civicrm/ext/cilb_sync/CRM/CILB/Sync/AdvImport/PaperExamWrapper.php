<?php

use League\Csv\Reader;

use CRM_CILB_Sync_ExtensionUtil as E;
use CRM_CILB_Sync_Utils as EU;

use Civi\Api4\Participant;
use Civi\Api4\PaperExamImportMap;

class CRM_CILB_Sync_AdvImport_PaperExamWrapper extends CRM_Advimport_Helper_Csv {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("Paper Exam Form Scores");
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
   * Validate the file type.
   */
  function validateUploadForm(&$form) {
    $valid = TRUE;

    $e = $form->controller->_pages['DataUpload']->getElement('uploadFile');
    $file = $e->getValue();
    $file['extension'] = pathinfo($file['name'], PATHINFO_EXTENSION);

    if ($file['type'] != 'text/plain' || $file['extension'] != 'txt') {
      $form->setElementError('uploadFile', "The selected source must be a TXT file");
      $valid = FALSE;
    }

    if ($valid ) {
      return parent::validateUploadForm($form);
    }

    return $valid;
  }


  /**
   * Returns the data from the file.
   * Fixed-width TXT file.
   *
   * @param $file
   * @param $delimiter
   * @param $encoding
   *
   * @returns Array
   */
  public function getDataFromFile($file, $delimiter = '', $encoding = 'UTF-8') {
    $delimiter = "";
    $headers = [0 => 'candidate_id', 1 => 'examscore'];

    // cannot use CSV Helper as we need to add missing headers
    $reader = Reader::createFromPath($file);
    $records = $reader->getRecords();

    // Re-key data using the headers
    $data = [];
    foreach ($records as $record) {
      $candidate_id = substr($record[0], 0, 6);
      $score = substr($record[0], 11, 5);
      $d = [
        'candidate_id' => $candidate_id,
        'examscore' => (float) (substr($score, -4, 2) . '.' . substr($score, -2)),
      ];
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
    $event_id = PaperExamImportMap::get(FALSE)->addWhere('advanced_import_id', '=', $params['advimport_id'])->execute()->first()['exam_id'] ?? NULL;
    if (empty($event_id)) {
      throw new \CRM_Core_Exception("Unable to process exam import as exam not been selected");
    }
    $candidateID    = $params['candidate_id'];
    $examScore      = $params['examscore'] ?? NULL;
    $examStatus     = (bool) ($examScore > 70);

    if (empty($candidateID)) {
      throw new \CRM_Core_Exception("Unable to process exam score as we are missing a Candidate ID");
    }

    // Get participation record matching CandidateID for a Paper-Based event
    $candidateRegistration = EU::getExamRegistrationFromCandidateID($candidateID, $event_id, 'Paper_based');
    if (empty($candidateRegistration)) {
      CRM_Advimport_Utils::logImportWarning($params, "Candidate Registration for candidate_id - {$candidateID} was not found");
      return;
    }

    $participantID = $candidateRegistration['id'];
    if ($examStatus) {
	    $examStatus = "Pass";
    }
    else {
	    $examStatus = "Fail";
    }

    try {
      Participant::update(FALSE)
        ->addValue('Candidate_Result.Candidate_Score', $examScore)
        ->addValue('status_id:name', $examStatus)
        ->addWhere('id', '=', $participantID)
        ->execute();
    }
    catch (\CRM_Core_Exception $e) {
      \CRM_Core_Error::debug_var('participant_api_error_message', $e->getMessage());
      throw new \CRM_Core_exception("Failed to update exam score due to API error. " . $e->getMessage());
    }
    // Succesfully updated.
    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_participant', $participantID);
  }

}
