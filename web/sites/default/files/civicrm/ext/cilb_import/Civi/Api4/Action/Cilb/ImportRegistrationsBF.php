<?php

namespace Civi\Api4\Action\Cilb;

/**
 * run with cv api4 on the command line
 *
 * e.g.
 * cv api4 Cilb.importRegistrationsBF sourceDsn=[] \
 *  cutOffDate=2019-09-01 \
 *  transactionYear=2020
 */
class ImportRegistrationsBF extends ImportBase {

  /**
   * @var string
   * @required
   *
   * 4 digit year to enable importing in segments
   */
  protected string $transactionYear;

  private array $eventMap = [];

  protected function import() {
    $this->info("Importing BF registrations for {$this->transactionYear}...");

    $this->buildEventMap();
    $this->importBusinessAndFinance();
  }

  protected function buildEventMap() {
    $events = \Civi\Api4\Event::get(FALSE)
      ->addSelect('id', 'event_type_id:name', 'Exam_Details.Exam_Part')
      ->addWhere('is_active', '=', TRUE)
      ->execute();

    foreach ($events as $event) {
      $type = $event['event_type_id:name'];
      $part = $event['Exam_Details.Exam_Part'];
      if (!$type || !$part) {
        // not relevant for us
        continue;
      }
      $this->eventMap[$type] ??= [];
      if (isset($this->eventMap[$type][$part])) {
        $this->warning("More than one event exists for {$type} {$part}. Registrations will be imported to event ID {$this->eventMap[$type][$part]} - event ID {$event['id']} will be ignored");
        continue;
      }
      $this->eventMap[$type][$part] = $event['id'];
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
        AND CBT_BF_Exam = '1'
    ") as $registration) {
      try {
        $this->importBusinessAndFinanceRow($registration);
      }
      catch (\Exception $e) {
        $this->warning($e->getMessage() . " when importing " . \json_encode($registration, \JSON_PRETTY_PRINT));
      }
    }
  }

  protected function importBusinessAndFinanceRow($registration) {
    $contactId = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
      ->execute()->first()['id'] ?? NULL;

    if (!$contactId) {
      throw new \Exception('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
    }

    $event = $this->eventMap[$registration['Category_Name']]['BF'] ?? NULL;

    if (!$event) {
      throw new \Exception("No event found for registration ID {$registration['PK_Exam_Registration_ID']}.");
    }

    /**
     * Note source data has 0, 1, and NULL
     */
    $status = match ($registration['BF_Pass']) {
      '1' => 'Pass',
      '0' => 'Fail',
      default => 'Registered',
    };

    try {
      \Civi\Api4\Participant::create(FALSE)
        ->addValue('event_id', $event)
        ->addValue('contact_id', $contactId)
        ->addValue('register_date', $registration['Transaction_Date'])
        ->addValue('Candidate_Result.Candidate_Score', $registration['BF_Score'])
        ->addValue('status_id:name', $status)
        ->execute();
    }
    catch (\Exception $e) {
      throw new \Exception('Participant.create failed for ' . \json_encode([
        'event_id' => $event,
        'contact_id' => $contactId,
        'register_date' => $registration['Transaction_Date'],
        'Candidate_Result.Candidate_Score' => $registration['BF_Score'],
        'status_id:name' => $status,
      ], \JSON_PRETTY_PRINT));
    }
  }

}
