<?php

use CRM_CILB_Sync_ExtensionUtil as E;

class CRM_CILB_Sync_AdvImport_PearsonVueWrapper extends CRM_CILB_Sync_AdvImport_Helper_Dat {

    /**
     * Returns a human-readable name for this helper.
     */
    function getHelperLabel() {
    return E::ts("PearsonVue Exam Scores");
    }

    /**
     * By default, a field mapping will be shown, but unless you have defined
     * one in getMapping() - example later below - you may want to skip it.
     * Displaying it is useful for debugging at first.
     */
    function mapfieldMethod() {
        return 'skip';
    }


    /**
     * Import an item gotten from the queue.
     *
     * This is where, in custom PHP import scripts, you would program all
     * the logic on how to handle imports the old fashioned way.
     */
    function processItem($params) {

        $row_id = $params['import_row_id'];
        $table_name = $params['import_table_name'];

        $examSeriesCode = $params['examseriescode'] ?? NULL;
        $candidateID    = $params['clientcandidateid'] ?? NULL;
        $examRegID      = $params['registrationid'] ?? NULL;
        $examDate       = $params['examdate'] ?? NULL;
        $examScore      = $params['examscore'] ?? NULL;
        $examStatus     = ucfirst($params['examgrade']) ?? NULL;

        // Sanity Checks
        if (empty($candidateID)) {
            throw new Exception("uploaded file is missing the Client Candidate ID.");
        }
        if (empty($examSeriesCode)) {
            throw new Exception("uploaded file is missing the Exam Series Code.");
        }
        if (empty($examDate) || empty($examScore) || empty($examStatus) || empty($examRegID)) {
            throw new Exception("uploaded file is missing the exam score information.");
        }

        // Exam Info
        $exam = getExamInfoFromSeriesCode($examSeriesCode);
        if ( $exam == NULL ) {
            //CRM_Advimport_Utils::logImportMessage($params, "Skipped", 0);
            /*CRM_Core_DAO::executeQuery("UPDATE $table_name SET import_status = %2, import_error = %3 where `row`= %1", [
                1 => [$row_id, 'Positive'],
                2 => [0, 'Positive'],
                3 => ["Skipped", 'String'],
            ]);*/
            CRM_Advimport_Utils::logImportWarning($params, "Skipped");
            return;
        }
        $examID = $exam['id'];
        $examClass = $exam['event_type.Exam_Type_Details.DBPR_Code'];

        // Candidate Info
        $candidate = getCandidateEntity($candidateID, $examClass);
        if ( $candidate == NULL ) {
            throw new Exception("Cannot find candidate [$candidateID, $examClass]");
        }

        // Exam Registration
        $contactID = $candidate['entity_id'];
        $participantResults = getExamRegistrationWithoutScore($contactID, $examID);
        if ( $participantResults->count() == 0 ) {
            // @TODO: log as skipped (warning) ?
            throw new Exception("No registration found that doesn't have a score already.");
        }
        if ( $participantResults->count() > 1 ) {
            throw new Exception("More than one registration found.");
        }

        $participantID = $participantResults->single()['id'];
        
        // Update Score / Date
        $result = \Civi\Api4\Participant::update(FALSE)
            ->addValue('source', $examRegID)
            ->addValue('Candidate_Result.Candidate_Score', $examScore)
            ->addValue('Candidate_Result.Date_Exam_Taken', $examDate)
            ->addValue('status_id:label', $examStatus)
            ->addWhere('id', '=', $participantID)
            ->execute();

        if (!empty($result['error_message'])) {
            throw new Exception("Failed to update exam score.");
        }

        // Succesfully updated.
        CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_participant', $participantID);
       
    }



}