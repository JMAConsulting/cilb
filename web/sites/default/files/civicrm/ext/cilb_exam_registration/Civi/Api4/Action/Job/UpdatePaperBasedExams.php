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
    $maxCandidateIDs = [];

    $participants = \Civi\Api4\Participant::get(FALSE)
      ->addSelect('id', 'event_id')
      ->addWhere('event_id.Exam_Details.Exam_Format', '=', 'paper')
      ->addWhere('Candidate_Result.Candidate_Number', 'IS EMPTY')
      ->execute();

    $result['values']['count'] = count($participants);
    $success = 0;

    foreach ($participants as $participant) {
      if (!array_key_exists($participant['event_id'], $maxCandidateIDs)) {
        $maxCandidateID = (int) \Civi\Api4\Participant::get(FALSE)
          ->addSelect('MAX(Candidate_Result.Candidate_Number) AS max_candidate_id')
          ->addWhere('event_id', '=', $participant['event_id'])
          ->execute()
          ->first()['max_candidate_id'];
        if (empty($maxCandidateID)) {
          $maxCandidateID = '570102';
        }
        else {
          $maxCandidateID = $maxCandidateID + 1;
        }
      }
      else {
        $maxCandidateID = $maxCandidateIDs[$participant['event_id']] + 1;
      }

      if ($maxCandidateID > 999999) {
        $result['is_error'] = TRUE;
        $result['error_message'] = 'Reached max Candidate_Number. Completed ' . $success . '/' . $result['values']['count'];
        $result['values']['failed'][] = $participant['id'];
        throw new CRM_Core_exception($result['error_message']);
      }

      $candidateID = str_pad($maxCandidateID, 6, '0', STR_PAD_LEFT); // ensure it's always 6 digits
      try {
        Participant::update(FALSE)
          ->addValue('Candidate_Result.Candidate_Number', $candidateID)
          ->addWhere('id', '=', $participant['id'])
          ->execute();
        $result['values']['updated'][] = [
          $participant['id'] => $candidateID
        ];
        $success += 1;
      }
      catch (CRM_Core_Exception $e) {
        $result['is_error'] = TRUE;
        $result['error_message'] = 'Failed to update candidate number [' . $participant['id'] . ']. Completed ' . $success . '/' . $result['values']['count'] . ' Error: ' . $e->getMessage();
        $result['values']['failed'][] = $participant['id'];
        CRM_Core_Error::debug_var('participant_api_error_message', $result['error_message']);
        throw new CRM_Core_exception($result['error_message']);
      }
      $maxCandidateIDs[$participant['event_id']] = (float) $candidateID;
    }

    return $result;
  }
}
