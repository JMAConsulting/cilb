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

  private array $eventMap = [];

  protected function import() {
    $this->info("Importing registrations for {$this->transactionYear}...");

    $this->info('Building event map...');
    $this->buildEventMap();

    $this->info('Importing main parts...');
    $this->importParts();
    $this->info('Importing business and finance...');
    $this->importBusinessAndFinance();
  }

  protected function buildEventMap() {
    $events = \Civi\Api4\Event::get(FALSE)
      ->addSelect('id', 'event_type_id:name', 'Exam_Details.Exam_Part')
      ->execute();

    foreach ($events as $event) {
      $type = $event['event_type_id:name'];
      $part = $event['Exam_Details.Exam_Part'];
      $this->eventMap[$type] ??= [];
      $this->eventMap[$type][$part] = $event['id'];
    }
  }

  protected function importParts() {
    foreach ($this->getRows("
        SELECT
          PK_Exam_Registration_ID,
          FK_Account_ID,
          pti_Exam_Registrations.FK_Category_ID,
          Category_Name,
          Transaction_Date,
          Exam_Part_Name_Abbr
        FROM pti_Exam_Registrations
        JOIN pti_Code_Categories
        ON `FK_Category_ID` = `PK_Category_ID`

        JOIN pti_Code_Exam_Parts
        ON pti_Exam_Registrations.`FK_Category_ID` = pti_Code_Exam_Parts.`FK_Category_ID`

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

      $event = $this->eventMap[$registration['Category_Name']][$registration['Exam_Part_Name_Abbr']] ?? NULL;

      if (!$event) {
        $debug = json_encode($registration);
        $this->warning("No event found for registration ID {$registration['PK_Exam_Registration_ID']}. ({$debug})");
        continue;
      }

      /**
       * Note source data has 0, 1, and NULL
       */
      //TODO this is missing
      $status = match ($registration['Pass'] ?? NULL) {
        '1' => 'Pass',
        '0' => 'Fail',
        default => 'Registered',
      };

      \Civi\Api4\Participant::create(FALSE)
        ->addValue('event_id', $event)
        ->addValue('contact_id', $contactId)
        ->addValue('register_date', $registration['Transaction_Date'])
     // TODO this is missing
     //   ->addValue('Candidate_Result.Candidate_Score', $registration['Score'])
        ->addValue('status_id:label', $status)
        ->execute();
    }
  }

  protected function importBusinessAndFinance() {
    foreach ($this->getRows("
        SELECT
          PK_Exam_Registration_ID,
          FK_Account_ID,
          FK_Category_ID,
          Category_Name,
          Confirm_BF_Exam,
          BF_Pass,
          BF_Score,
          Transaction_Date
        FROM pti_Exam_Registrations
        JOIN pti_Code_Categories
        ON `FK_Category_ID` = `PK_Category_ID`
        WHERE Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(Transaction_Date) = '{$this->transactionYear}'
        AND Confirm_BF_Exam IS NOT NULL
    ") as $registration) {

      $contactId = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
        ->execute()->first()['id'] ?? NULL;

      if (!$contactId) {
        $this->warning('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
        continue;
      }

      $event = $this->eventMap[$registration['Category_Name']]['BF'] ?? NULL;

      if (!$event) {
        $debug = json_encode($registration);
        $this->warning("No event found for registration ID {$registration['PK_Exam_Registration_ID']}. ({$debug})");
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
        ->addValue('event_id', $event)
        ->addValue('contact_id', $contactId)
        ->addValue('register_date', $registration['Transaction_Date'])
        ->addValue('Candidate_Result.Candidate_Score', $registration['BF_Score'])
        ->addValue('status_id:label', $status)
        ->execute();
    }
  }

}
