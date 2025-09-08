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
          PK_Exam_Registration_Part_ID,
          FK_Account_ID,
          FK_Category_ID,
          Category_Name,
          Confirm_BF_Exam,
          BF_Pass,
          BF_Score,
          Fee_Amount,
          Payment_Method,
          Seat_Fee_Amount,
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

    
    $participant = \Civi\Api4\Participant::create(FALSE)
      ->addValue('event_id', $event)
      ->addValue('contact_id', $contactId)
      ->addValue('register_date', $registration['Transaction_Date'])
      ->addValue('Candidate_Result.Candidate_Score', $registration['BF_Score'])
      ->addValue('status_id:name', $status)
      ->execute();

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
      civicrm_api3('ParticipantPayment', 'create', [
        'participant_id' => $participant['id'],
        'contribution_id' => $payment,
      ]);
      // Also update the participant record with the fee amount
      \Civi\Api4\Participant::update(FALSE)
        ->addWhere('id', '=', $participant['id'])
        ->addValue('Participant_Webform.Candidate_Payment', $payment)
        ->addValue('fee_amount', $registration['Fee_Amount'])
        ->addValue('fee_level', 'Registration Fee')
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

      civicrm_api3('ParticipantPayment', 'create', [
        'participant_id' => $participant['id'],
        'contribution_id' => $contribution['id'],
      ]);
      // Also update the participant record with the fee amount
      \Civi\Api4\Participant::update(FALSE)
        ->addWhere('id', '=', $participant['id'])
        ->addValue('Participant_Webform.Candidate_Payment', $contribution['id'])
        ->addValue('participant_fee_amount', $registration['Fee_Amount'])
        ->addValue('participant_fee_level', 'Registration Fee')
        ->execute();

      // If there is a Seat Fee, add that as a separate line item.
      if (!empty($registration['Seat_Amount'])) {
        $priceSetByEventId = \Civi\Api4\PriceSetEntity::get(FALSE)
          ->addSelect('price_set_id')
          ->addWhere('entity_table', '=', 'civicrm_event')
          ->addWhere('entity_id', '=', $event)
          ->execute()->first()['price_set_id'] ?? NULL;

        if ($priceSetByEventId) {
          $priceOptions = (array) \Civi\Api4\PriceFieldValue::get(FALSE)
            ->addWhere('price_field_id.price_set_id', '=', $priceSetByEventId)
            ->addSelect(
              // price field value fields
              'id',
              'price_field_id',
              'label'
            )
            ->execute();

          $params = [
            'entity_id' => $participant['id'],
            'entity_table' => 'civicrm_participant',
            'contribution_id' => $contribution['id'],
            'participant_count' => 1,
            // from getEventFees
            'price_field_value_id' => $priceOptions['id'],
            'price_field_id' => $priceOptions['price_field_id'],
            'qty' => 1,
            'unit_price' => $registration['Seat_Amount'],
            'line_total' => $registration['Seat_Amount'],
            'financial_type_id' => 4,
            'label' => "CILB Candidate Registration - {$priceOptions['label']}",
          ];
          \CRM_Price_BAO_LineItem::create($params);
          

          $totalFee = (float)$registration['Fee_Amount'] + (float)$registration['Seat_Amount'];
          \Civi\Api4\Participant::update(FALSE)
            ->addWhere('id', '=', $participant['id'])
            ->addValue('participant_fee_amount', $totalFee)
            ->addValue('participant_fee_level', $priceOptions['label'])
            ->execute();
          \CRM_Core_DAO::executeQuery(<<<SQL
            UPDATE `civicrm_contribution`
            SET
              `total_amount` = {$totalFee}
            WHERE `id` = {$contribution['id']}
            SQL);
        }
      }
    }
  }
}
