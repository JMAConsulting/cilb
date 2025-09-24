<?php

use Civi\Api4\Contact;
use Civi\Api4\CustomValue;
use Civi\Api4\Event;
use Civi\Api4\OptionValue;
use Civi\Api4\Participant;
use Civi\Api4\Generic\Result;

class CRM_CILB_Sync_Utils {

  public const ADV_IMPORT_FOLDER = 'advimport';

  public static function getTimestampDate($date) {
    
    // Validate date if provided
    if ( !empty($date) ) {
      $realDate = strtotime($date);
    } else {
      $realDate = strtotime('now');
    }

    if ( !$realDate ) {
      throw new Exception("Invalid date");
    }

    return $realDate;
  }

  public static function getExamRegistrationWithoutScore($contactID, $examID): Result {
    $participant = Participant::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $contactID)
      ->addWhere('event_id', '=', $examID)
      ->addWhere('Candidate_Result.Candidate_Score', 'IS EMPTY')
      ->addWhere('Candidate_Result.Date_Exam_Taken', 'IS NULL')
      ->execute();

    return $participant;
  }


  public static function getCandidateEntity($candidateID, $classCode): ?array {
    $candidateEntity = CustomValue::get('cilb_candidate_entity', FALSE)
      ->addWhere('Entity_ID_imported_', '=', (int) $candidateID) // cast as Integer to remove leading 0
      ->addWhere('class_code', '=', $classCode)
      ->addOrderBy('Entity_ID_imported_', 'ASC')
      ->execute()
      ->first();
    return $candidateEntity;
  }

  public static function getCandidateEntityFromExternalID($externalID, $candidateID, $classCode): ?array {
    $contact = \Civi\Api4\Contact::get(TRUE)
      ->addSelect('id', 'custom_cilb_candidate_entity.*')
      ->addJoin('Custom_cilb_candidate_entity AS custom_cilb_candidate_entity', 
        'LEFT', 
        ['custom_cilb_candidate_entity.entity_id', '=', 'id'], 
        ['custom_cilb_candidate_entity.Entity_ID_imported_', '=', (int) $candidateID], // cast as Integer to remove leading 0
        ['custom_cilb_candidate_entity.class_code', '=', $classCode]
      )
      ->addWhere('external_identifier', '=', $externalID)
      ->execute()
      ->first();
    return $contact;
  }

  public static function getExamInfoFromSeriesCode($seriesCode): ?array {
    $exam = Event::get(FALSE)
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

  public static function getExamInfoFromClassCode($classCode): ?array {
    $examCategory  = OptionValue::get(FALSE)
      ->addSelect('label', 'value', 'custom.*')
      ->addWhere('Exam_Type_Details.DBPR_Code', '=', $classCode)
      ->addWhere('option_group_id:name', '=', 'event_type')
      ->execute()
      ->first();
    return $examCategory;
  }

}