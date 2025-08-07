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

    $this->buildEventMap();
    $this->importParts();
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
          Exam_Part_Name_Abbr,
          Pass,
          Score
        FROM pti_Exam_Registrations

        JOIN pti_Code_Categories
        ON `FK_Category_ID` = `PK_Category_ID`

        JOIN pti_Code_Exam_Parts
        ON pti_Exam_Registrations.`FK_Category_ID` = pti_Code_Exam_Parts.`FK_Category_ID`

        JOIN pti_Exam_Registration_Parts
        ON pti_Exam_Registration_Parts.`FK_Exam_Registration_ID` = pti_Exam_Registrations.`PK_Exam_Registration_ID`

        WHERE Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(Transaction_Date) = '{$this->transactionYear}'
        AND Exam_Part_Name_Abbr != 'BF'
    ") as $registration) {
      try {
        $this->importRegistrationRow($registration);
      }
      catch (\Exception $e) {
        $this->warning($e->getMessage() . " when importing " . \json_encode($registration, \JSON_PRETTY_PRINT));
      }
    }
  }

  protected function importRegistrationRow($registration) {
    $contactId = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
      ->execute()->first()['id'] ?? NULL;

    if (!$contactId) {
      throw new \Exception('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
    }

    $event = $this->eventMap[$registration['Category_Name']][$registration['Exam_Part_Name_Abbr']] ?? NULL;

    if (!$event) {
      throw new \Exception("No event found for registration ID {$registration['PK_Exam_Registration_ID']}");
    }

    /**
     * Note source data has 0, 1, and NULL
     */
    $status = match ($registration['Pass'] ?? NULL) {
      '1' => 'Pass',
      '0' => 'Fail',
      default => 'Registered',
    };

    \Civi\Api4\Participant::create(FALSE)
      ->addValue('event_id', $event)
      ->addValue('contact_id', $contactId)
      ->addValue('register_date', $registration['Transaction_Date'])
      ->addValue('Candidate_Result.Candidate_Score', $registration['Score'])
      ->addValue('status_id:name', $status)
      ->execute();
  }

}
