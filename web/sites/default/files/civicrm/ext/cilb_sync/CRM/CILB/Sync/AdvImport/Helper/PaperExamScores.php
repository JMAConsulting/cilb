<?php

use CRM_CILB_Sync_ExtensionUtil as E;
use CRM_CILB_Sync_Utils as EU;
use League\Csv\Reader;

class CRM_CILB_Sync_AdvImport_Helper_PaperExamScores extends CRM_Advimport_Helper_Csv {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("Paper Exam Form Scores");
  }

  public function validateUploadForm($form) {
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

  public function getDataFromFile($file, $delimiter = '', $encoding = 'UTF-8') {
    $reader = Reader::createFromPath($file);
    $records = $reader->getRecords();
    $data = [];
    $headers = [0 => 'candidate_number', 1 => 'score1', 2 => 'score2', 3 => 'score3'];
    foreach ($records as $record) {
      $candidate_id = substr($record[0], 0, 6);
      $score1 = substr($record[0], 6, 5);
      $score2 = substr($record[0], 11, 5);
      $score3 = substr($record[0], 16, 6);
      $d = [
        $candidate_id,
        $score1,
        $score2,
        $score3,
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
    $candidate_id = $params['candidate_id'];
    $score = $params['score2'];
    $parsedScore = (float) (substr($score, -4, 2) . '.' . substr($score, -2));
    $pass = (bool) ($parsedScore > 70);
    if (empty($candidate_id) || empty($score)) {
      throw new \CRM_Core_Exception("Unable to process exam score as we are missing either a candidate_id or the score");
    }
    $candidateRegistration = EU::getExamRegistrationFromCandidateID($candidate_id);
    if (empty($candidateRegistration)) {
      CRM_Advimport_Utils::logImportWarning($params, "Candidate Registration for candidate_id - {$candidate_id} was not found");
    }
    try {
      Participant::update(FALSE)
        ->addValue('Candidate_Result.Candidate_Score', $parsedScore)
        ->addValue('status_id:name', $pass ? 'Pass' : 'Fail')
        ->addWhere('id', '=', $candidateRegistration)
        ->execute();
    }
    catch (\CRM_Core_Exception $e) {
      \CRM_Core_Error::debug_var('participant_api_error_message', $e->getMessage());
      throw new \CRM_Core_exception("Failed to update exam score.");
    }
    // Succesfully updated.
    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_participant', $candidateRegistration['id']);
  }

}