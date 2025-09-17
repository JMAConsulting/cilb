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

  protected function import() {
    $this->info("Importing BF registrations for {$this->transactionYear}...");

    $this->buildEventMap();
    $this->importBusinessAndFinance();
  }

  /**
   * Override for BF exceptions
   */
  protected function buildEventMap() {
    $eventOptionCategories = \Civi\Api4\OptionValue::get(FALSE)->addWhere('option_group_id:name', '=', 'event_type')->exceute();
    $eventCategories = [];
    $bfCategories = [];
    foreach ($eventOptionCategories as $eventOptionCategory) {
      if (in_array($eventOptionCategory['name'], ['Business and Finance', 'Pool & Spa Servicing Business and Finance'])) {
        $bfCategories[] = $eventOptionCategory['name'];
      }
      if ($eventOptionCategory['name'] !== 'Pool/Spa Servicing') {
        $eventCategories[$eventOptionCategory['name']] = 'Pool & Spa Servicing Business and Finance';
      }
      else {
        $eventCategories[$eventOptionCategory['name']] = 'Business and Finance';
      }
    }

    $events = \Civi\Api4\Event::get(FALSE)
      ->addSelect('id', 'event_type_id:name', 'Exam_Details.Exam_Part', 'Exam_Details.Exam_Category_this_exam_applies_to:name')
      ->addWhere('is_active', '=', TRUE)
      ->execute();

    foreach ($events as $event) {
      $type = $event['event_type_id:name'];
      $part = $event['Exam_Details.Exam_Part'];
      if (!$type || !$part || !in_array($type, $bfCategories)) {
        // not relevant for us
        continue;
      }
      foreach ($eventCategories as $eventCategory => $bfEventType) {
        $this->eventMap[$eventCategory] ??= [];
        if (isset($this->eventMap[$eventCategory][$part])) {
          $this->warning("More than one event exists for {$eventCategory} {$part}. Registrations will be imported to event ID {$this->eventMap[$eventCategory][$part]} - event ID {$event['id']} will be ignored");
          continue;
        }
        if ($type == $bfEventType) {
          $this->eventMap[$eventCategory][$part] = $event['id'];
        }
      }
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
          FK_Exam_Event_ID,
          BF_Score,
          BF_Exam_Date,
          Fee_Amount,
          Payment_Method,
          Seat_Fee_Amount,
          Candidate_Number,
          Registration_Status,
          Check_Number,
          Transaction_Date
        FROM pti_Exam_Registrations
        JOIN pti_Code_Categories
        ON `FK_Category_ID` = `PK_Category_ID`
        WHERE Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(Transaction_Date) = '{$this->transactionYear}'
        AND CBT_BF_Exam = '1'
        AND Registration_Status IN ('Registration Complete', 'Registration Paid')
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
      return;
      //throw new \Exception('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
    }

    $event = $this->eventMap[$registration['Category_Name']]['BF'] ?? NULL;

    if (!$event) {
      return;
      //throw new \Exception("No event found for registration ID {$registration['PK_Exam_Registration_ID']}.");
    }

    /**
     * Note source data has 0, 1, and NULL
     */
    $status = match ($registration['BF_Pass']) {
      '1' => 'Pass',
      '0' => 'Fail',
      default => 'Registered',
    };

    $participantId = \Civi\Api4\Participant::create(FALSE)
      ->addValue('event_id', $event)
      ->addValue('contact_id', $contactId)
      ->addValue('register_date', $registration['Transaction_Date'])
      ->addValue('Candidate_Result.Candidate_Score', $registration['BF_Score'])
      ->addValue('Candidate_Result.Candidate_Number', $registration['Candidate_Number'])
      ->addValue('Candidate_Result.Date_Exam_Taken', $registration['BF_Exam_Date'])
      ->addValue('status_id:name', $status)
      ->execute()->first()['id'];

    // Update the exam location as well.
    if (!empty($registration['FK_Exam_Event_ID'])) {
      $this->updateExamLocation($registration['FK_Exam_Event_ID'], $event);
    }

    $this->recordPayments(
      $registration,
      $contactId,
      $participantId,
      $event,
      NULL, // all seat fees are external
      "CILB Import: Account ID ({$registration['FK_Account_ID']}) - Registration ID ({$registration['PK_Exam_Registration_ID']})"
    );
  }
}
