<?php

use Civi\Api4\Generic\Result;
use CRM_CILB_Sync_ExtensionUtil as E;

class CRM_CILB_Sync_Utils {

  public static function getExamRegistrationWithoutScore($contactID, $examID): Result {
    $participant = \Civi\Api4\Participant::get(FALSE)
      ->addSelect('id')
      ->addWhere('contact_id', '=', $contactID)
      ->addWhere('event_id', '=', $examID)
      ->addWhere('Candidate_Result.Candidate_Score', 'IS EMPTY')
      ->addWhere('Candidate_Result.Date_Exam_Taken', 'IS NULL')
      ->execute();

    return $participant;
  }


  public static function getCandidateEntity($candidateID, $classCode): ?array {
    $candidateEntity = \Civi\Api4\CustomValue::get('cilb_candidate_entity', FALSE)
      ->addWhere('Entity_ID_imported_', '=', (int) $candidateID) // cast as Integer to remove leading 0
      ->addWhere('class_code', '=', $classCode)
      ->addOrderBy('Entity_ID_imported_', 'ASC')
      ->execute()
      ->first();
    return $candidateEntity;
  }

  public static function getExamInfoFromSeriesCode($seriesCode): ?array {
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

  public static function getCandidateContactIDFromExternalIdentifier($eternalIdentifier): Result {
    $contact = Contact::get(FALSE)
      ->addWhere('external_identifier', '=', $eternalIdentifier)
      ->execute();
    return $contact;
  }

  public static function getExamCategoryFromClassCode($classCode): ?array {
    $examCategory  = \Civi\Api4\OptionValue::get(FALSE)
      ->addWhere('Exam_Type_Details.DBPR_Code', '=', $classCode)
      ->addWhere('option_group_id:name', '=', 'event_type')
      ->execute()
      ->first();
    return $examCategory;
  }

}