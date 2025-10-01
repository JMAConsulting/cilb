<?php

namespace Civi\Api4\Action\Job;

use Civi\Api4\Generic\Result;
use Civi\Api4\Participant;
use CRM_Core_Error;
use CRM_Core_Exception;


/**
 * Automatically download CILB Exam files and trigger an import
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
        ->addSelect('MAX(Candidate_Result.Candidate_Number) AS max_candiate_id')
        ->execute()
        ->first()['max_candiate_id'];
  

    $participantIDs = \Civi\Api4\Participant::get(FALSE)
        ->addSelect('id')
        ->addWhere('event_id.Exam_Details.Exam_Format', '=', 'paper')
        ->addWhere('Candidate_Result.Candidate_Number', 'IS EMPTY')
        ->execute()
        ->column('id');

    $result['count'] = count($participantIDs);
    
    foreach ($participantIDs as $participantID) {
        $maxCandidateID += 1;

        if ($maxCandidateID > 999999) {
            $result['failed'][] = $participantID;
            throw new CRM_Core_exception('Reached max Candidate_Number.');
        }

        $candidateID = str_pad($maxCandidateID, 6, '0', STR_PAD_LEFT); // ensure it's always 6 digits
        try {
            Participant::update(FALSE)
                ->addValue('Candidate_Result.Candidate_Number', $candidateID)
                ->addWhere('id', '=', $participantID)
                ->execute();
            $result['updated'][] = [
                'id' => $participantID,
                'Candidate_Result.Candidate_Number' => $candidateID
            ];
        }
        catch (CRM_Core_Exception $e) {
            $result['failed'][] = $participantID;
            CRM_Core_Error::debug_var('participant_api_error_message', $e->getMessage());
            throw new CRM_Core_exception("Failed to update candidate number.");
        }
    }

    return $result;
  }
  
}
