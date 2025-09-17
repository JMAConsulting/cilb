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

  protected function importParts() {
    foreach ($this->getRows("
        SELECT
          PK_Exam_Registration_ID,
          PK_Exam_Registration_Part_ID,
          FK_Account_ID,
          pti_Exam_Registrations.FK_Category_ID,
          Category_Name,
          Transaction_Date,
          Exam_Part_Name_Abbr,
          Candidate_Number,
          CBT_Exam_Date,
          FK_Exam_Event_ID,
          Pass,
          Fee_Amount,
          Payment_Method,
          Seat_Fee_Amount,
          Registration_Status,
          Check_Number,
          Score
        FROM pti_Exam_Registrations

        JOIN pti_Code_Categories
        ON `FK_Category_ID` = `PK_Category_ID`

        JOIN pti_Exam_Registration_Parts
        ON pti_Exam_Registration_Parts.`FK_Exam_Registration_ID` = pti_Exam_Registrations.`PK_Exam_Registration_ID`

        JOIN pti_Code_Exam_Parts
        ON pti_Exam_Registration_Parts.`FK_Exam_Part_ID` = pti_Code_Exam_Parts.`PK_Exam_Part_ID`

        WHERE
        Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(Transaction_Date) = '{$this->transactionYear}'
        AND Exam_Part_Name_Abbr != 'BF'
        AND Registration_Status IN ('Registration Complete', 'Registration Paid')
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
      return;
      //throw new \Exception('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
    }

    $event = $this->eventMap[$registration['Category_Name']][$registration['Exam_Part_Name_Abbr']] ?? NULL;

    if (!$event) {
      return;
      //throw new \Exception("No event found for registration ID {$registration['PK_Exam_Registration_ID']}");
    }

    /**
     * Note source data has 0, 1, and NULL
     */
    $status = match ($registration['Pass'] ?? NULL) {
      '1' => 'Pass',
      '0' => 'Fail',
      default => 'Registered',
    };

    $participant = \Civi\Api4\Participant::create(FALSE)
      ->addValue('event_id', $event)
      ->addValue('contact_id', $contactId)
      ->addValue('register_date', $registration['Transaction_Date'])
      ->addValue('Candidate_Result.Candidate_Score', $registration['Score'])
      ->addValue('status_id:name', $status)
      ->addValue('Candidate_Result.Date_Exam_Taken', $registration['CBT_Exam_Date'])
      ->addValue('source', $registration['PK_Exam_Registration_Part_ID'])
      ->addValue('Candidate_Result.Candidate_Number', $registration['Candidate_Number'])
      ->execute()->first();

    // Update the exam location as well.
    if (!empty($registration['FK_Exam_Event_ID'])) {
      $this->updateExamLocation($registration['FK_Exam_Event_ID'], $event);
    }

    $paymentMethod = match ($registration['Payment_Method'] ?? NULL) {
      'Check' => 'Check',
      'Credit Card' => 'Credit Card',
      'Cash' => 'Cash',
      'Money Order/Cashier' => 'Money Order',
      'Money Order/Cashier Check' => 'Money Order',
      'Prepaid Online' => 'Prepaid Online',
      'PTI Voucher' => 'Voucher',
      'Voucher' => 'Voucher',
      'Visa' => 'Credit Card',
      'MasterCard' => 'Credit Card',
      default => 'Other',
    };

    // First search for an existing contribution for this registration
    $payment = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('trxn_id', '=', $registration['PK_Exam_Registration_ID'])
      ->execute()->first()['id'] ?? 0;
    if (!empty($payment)) {
      // The transaction already exists, only associate with the new participant record
      \Civi\Api4\Participant::update(FALSE)
        ->addWhere('id', '=', $participant['id'])
        ->addValue('Participant_Webform.Candidate_Payment', $payment)
        ->execute();
    }
    elseif (!empty($registration['Fee_Amount'])) {
      // Create a contribution record for the registration fee
      $contribution = \Civi\Api4\Contribution::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('total_amount', $registration['Fee_Amount'])
        ->addValue('receive_date', $registration['Transaction_Date'])
        ->addValue('financial_type_id', 4) // Exam Fees
        ->addValue('payment_instrument_id:name', $paymentMethod)
        ->addValue('contribution_status_id:name', 'Completed')
        ->addValue('trxn_id', $registration['PK_Exam_Registration_ID'])
        ->addValue('check_number', $registration['Check_Number'])
        ->addValue('source', 'CILB Import: Account ID (' . $registration['FK_Account_ID'] . ') - Registration Part ID (' . $registration['PK_Exam_Registration_Part_ID'] . ')')
        ->execute()->first();

      // Also update the participant record with the fee amount
      \Civi\Api4\Participant::update(FALSE)
        ->addWhere('id', '=', $participant['id'])
        ->addValue('Participant_Webform.Candidate_Payment', $contribution['id'])
        ->execute();
    }
  }
}
