<?php

namespace Civi\Api4\Action\Job;

use Civi\Api4\Generic\Result;
use Civi\Api4\Participant;
use CRM_Core_Error;
use CRM_Core_Exception;


/**
 * Generates Candidate Number for paper-based exams that don't have one assigned yet
 */

class UpdatePaperBasedExams extends \Civi\Api4\Generic\AbstractAction {

  /**
   * @var bool
   */
  protected $runInNonProductionEnvironment = TRUE;

  /**
   * @var null
   */
  protected $language = NULL;
  
  /**
   * @var null
   */
  protected $chain = [];

  /**
   * Runs the action
   */
  public function _run(Result $result) {

    $maxCandidateID = (int) \Civi\Api4\Participant::get(FALSE)
        ->addSelect('MAX(Candidate_Result.Candidate_Number) AS max_candidate_id')
        ->execute()
        ->first()['max_candidate_id'];

    // Just in case
    if ($maxCandidateID == 0) {
        $result['is_error'] = TRUE;
        $result['error_message'] = 'Error retrieving number for Candidate ID.';
        throw new CRM_Core_exception($result['error_message']);
    }
  

    $participantIDs = \Civi\Api4\Participant::get(FALSE)
        ->addSelect('id')
        ->addWhere('event_id.Exam_Details.Exam_Format', '=', 'paper')
        ->addWhere('Candidate_Result.Candidate_Number', 'IS EMPTY')
        ->execute()
        ->column('id');

    $result['values']['count'] = count($participantIDs);
    $success = 0;
    
    foreach ($participantIDs as $participantID) {
        $maxCandidateID += 1;

        if ($maxCandidateID > 999999) {

            $result['is_error'] = TRUE;
            $result['error_message'] = 'Reached max Candidate_Number. Completed ' . $success . '/' . $result['values']['count'];
            $result['values']['failed'][] = $participantID;
            throw new CRM_Core_exception($result['error_message']);
        }

        $candidateID = str_pad($maxCandidateID, 6, '0', STR_PAD_LEFT); // ensure it's always 6 digits
        try {
            Participant::update(FALSE)
                ->addValue('Candidate_Result.Candidate_Number', $candidateID)
                ->addWhere('id', '=', $participantID)
                ->execute();
            $result['values']['updated'][] = [
                $participantID => $candidateID
            ];
            $success += 1;
        }
        catch (CRM_Core_Exception $e) {
            $result['is_error'] = TRUE;
            $result['error_message'] = 'Failed to update candidate number ['.$participantID.']. Completed ' . $success . '/' . $result['values']['count'] . ' Error: ' . $e->getMessage();
            $result['values']['failed'][] = $participantID;
            CRM_Core_Error::debug_var('participant_api_error_message',$result['error_message'] );
            throw new CRM_Core_exception($result['error_message']);
        }
    }

    return $result;
  }
  
}
