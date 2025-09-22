<?php

require_once 'cilb_sync.civix.php';

use Civi\Api4\Generic\Result;
use CRM_CILB_Sync_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cilb_sync_civicrm_config(&$config): void {
  _cilb_sync_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cilb_sync_civicrm_install(): void {
  _cilb_sync_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cilb_sync_civicrm_enable(): void {
  _cilb_sync_civix_civicrm_enable();
}


/**
 * Custom Import Wrapper for migrating score data
 * Implements hook_civicrm_advimport_helpers()
 */
function cilb_sync_civicrm_advimport_helpers(&$helpers) {
  $helpers[] = [
    'class' => 'CRM_CILB_Sync_AdvImport_PearsonVueWrapper',
    'label' => E::ts('PearsonVue Import'),
  ];
}


function getExamRegistrationWithoutScore($contactID, $examID): Result {
  $participant = \Civi\Api4\Participant::get(FALSE)
    ->addSelect('id')
    ->addWhere('contact_id', '=', $contactID)
    ->addWhere('event_id', '=', $examID)
    ->addWhere('Candidate_Result.Candidate_Score', 'IS EMPTY')
    ->addWhere('Candidate_Result.Date_Exam_Taken', 'IS NULL')
    ->execute();
  
  return $participant;
}


function getCandidateEntity($candidateID, $classCode): array | NULL {
  
  $candidateEntity = \Civi\Api4\CustomValue::get('cilb_candidate_entity', FALSE)
    ->addWhere('Entity_ID_imported_', '=', (int) $candidateID) // cast as Integer to remove leading 0
    ->addWhere('class_code', '=', $classCode)
    ->addOrderBy('Entity_ID_imported_', 'ASC')
    ->execute()
    ->first();

  return $candidateEntity;  
}

function getExamInfoFromSeriesCode($seriesCode) : array | NULL {
  $exam = \Civi\Api4\Event::get(FALSE)
    ->addSelect(
      'Exam_Details.Exam_Series_Code', 
      'event_type_id:name', 
      'event_type.Exam_Type_Details.CILB_Class', 
      'event_type.Exam_Type_Details.DBPR_Code', 
      'event_type.Exam_Type_Details.Gus_DBPR_Code' // same as DBPR_Code, but without leading 0
    )
    ->addJoin('OptionValue AS event_type', 'INNER', ['event_type.value', '=', 'event_type_id'])
    ->addWhere('Exam_Details.Exam_Series_Code', '=', $seriesCode)
    ->setHaving([['event_type.Exam_Type_Details.CILB_Class', 'IS NOT NULL'],]) // needed to fix query (likely core bug)
    ->execute()
    ->first();
  
  return $exam;

}

