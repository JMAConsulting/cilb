<?php

namespace Civi\Api4\Action\Cilb;

/**
 * run with cv api4 on the command line
 *
 * e.g.
 * cv api4 Cilb.importRegistrations sourceDsn=[] \
 *  cutOffDate=2019-09-01 \
 *  transactionYear=2020
 */
class ImportRegistrations extends ImportBase {

  /**
   * @var string
   * @required
   *
   * 4 digit year to enable importing in segments
   */
  protected string $transactionYear;

  protected function import() {
    $this->importParts();
    $this->importBusinessAndFinance();
  }

  protected function importParts() {
    foreach ($this->getRows("
        SELECT
          PK_Exam_Registration_ID,
          FK_Account_ID,
          pti_exam_registrations.FK_Category_ID,
          Category_Name,
          Transaction_Date,
          Exam_Part_Name_Abbr,
          Pass,
          Score
        FROM pti_exam_registrations
        JOIN pti_code_categories
        ON `FK_Category_ID` = `PK_Category_ID`
        JOIN pti_exam_registration_parts
        ON `FK_Exam_Registration_ID` = `PK_Exam_Registration_ID`
        JOIN pti_code_exam_parts
        ON `FK_Exam_Part_ID` = `PK_Exam_Part_ID`
        WHERE Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(Transaction_Date) = '{$this->transactionYear}'
    ") as $registration) {

      $contactId = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
        ->execute()->first()['id'] ?? NULL;

      if (!$contactId) {
        \Civi::log()->warning('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
        continue;
      }

      $event = \Civi\Api4\Event::get(FALSE)
        ->addSelect('id')
        ->addWhere('event_type_id:name', '=', $registration['Category_Name'])
        ->addWhere('Exam_Details.Exam_Part', '=', $registration['Exam_Part_Name_Abbr'])
        ->execute()
        ->first();

      if (!$event) {
        $debug = json_encode($registration);
        \Civi::log()->warning("No event found for registration ID {$registration['PK_Exam_Registration_ID']}. ({$debug})");
      }

      /**
       * Note source data has 0, 1, and NULL
       */
      $status = match ($registration['Pass']) {
        '1' => 'Pass',
        '0' => 'Fail',
        default => 'Registered',
      };

      \Civi\Api4\Participant::create(FALSE)
        ->addValue('event_id', $event['id'])
        ->addValue('contact_id', $contactId)
        ->addValue('register_date', $registration['Transaction_Date'])
        ->addValue('Candidate_Result.Candidate_Score', $registration['Score'])
        ->addValue('status_id:label', $status)
        ->execute();
    }
  }

  protected function importBusinessAndFinance() {
    foreach ($this->getRows("
        SELECT PK_Exam_Registration_ID, FK_Account_ID, FK_Category_ID, Category_Name, Confirm_BF_Exam, BF_Pass, Transaction_Date
        FROM pti_exam_registrations
        JOIN pti_code_categories
        ON `FK_Category_ID` = `PK_Category_ID`
        WHERE Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(Transaction_Date) = '{$this->transactionYear}'
        AND Confirm_BF_Exam IS NOT NULL
    ") as $registration) {

      $contactId = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
        ->execute()->first()['id'] ?? NULL;

      if (!$contactId) {
        \Civi::log()->warning('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
        continue;
      }

      $event = \Civi\Api4\Event::get(FALSE)
        ->addSelect('id')
        ->addWhere('event_type_id:name', '=', $registration['Category_Name'])
        ->addWhere('Exam_Details.Exam_Part', '=', 'BF')
        ->execute()
        ->first();

      if (!$event) {
        $debug = json_encode($registration);
        Civi::log()->warning("No event found for registration ID {$registration['PK_Exam_Registration_ID']}. ({$debug})");
      }

      /**
       * Note source data has 0, 1, and NULL
       */
      $status = match ($registration['BF_Pass']) {
        '1' => 'Pass',
        '0' => 'Fail',
        default => 'Registered',
      };

      \Civi\Api4\Participant::create(FALSE)
        ->addValue('event_id', $event['id'])
        ->addValue('contact_id', $contactId)
        ->addValue('register_date', $registration['Transaction_Date'])
        ->addValue('status_id:label', $status)
        ->execute();
    }
  }

}
