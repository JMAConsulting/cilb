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
abstract class ImportRegistrationsBase extends ImportBase {

  /**
   * @var string
   * @required
   *
   * 4 digit year to enable importing in segments
   */
  protected string $transactionYear;

  protected array $eventMap = [];

  private ?array $registrationFeePriceFieldValue = NULL;

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

  protected function recordPayments($registration, $contactId, $participantId, $eventId, $seatFeePaid, $source) {
    // First search for an existing contribution for this registration
    $contributionId = \Civi\Api4\Contribution::get(FALSE)
      ->addSelect('id')
      ->addWhere('trxn_id', '=', $registration['PK_Exam_Registration_ID'])
      ->execute()->first()['id'] ?? NULL;

    if (!$contributionId) {
      if ($registration['Fee_Amount']) {
        $contributionId = $this->createRegistrationContribution($registration, $contactId, $source);
      }
      else {
        throw new \CRM_Core_Exception('Could not find or create registration contribution');
      }
    }

    // Link the participant and contribution records using our custom link
    \Civi\Api4\Participant::update(FALSE)
      ->addWhere('id', '=', $participantId)
      ->addValue('Participant_Webform.Candidate_Payment', $contributionId)
      ->execute();

    // If there is a Seat Fee, add that as a separate line item.
    if ($seatFeePaid) {
      $this->addSeatFee($participantId, $eventId, $contributionId, $seatFeePaid);
    }
  }

  protected function createRegistrationContribution($registration, $contactId, $source) {
    // fetch price field value first time
    if (!$this->registrationFeePriceFieldValue) {
      $this->registrationFeePriceFieldValue = \Civi\Api4\PriceFieldValue::get(FALSE)
        ->addWhere('name', '=', 'Registration_Form_Fee')
        ->execute()
        ->first();
    }
    if (!$this->registrationFeePriceFieldValue) {
      throw new \CRM_Core_Exception('Couldnt find registration price field value');
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

    $contribution = \Civi\Api4\Contribution::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('total_amount', $registration['Fee_Amount'])
      ->addValue('receive_date', $registration['Transaction_Date'])
      ->addValue('financial_type_id', 4) // Exam Fees
      ->addValue('payment_instrument_id:name', $paymentMethod)
      ->addValue('contribution_status_id:name', 'Completed')
      ->addValue('trxn_id', $registration['PK_Exam_Registration_ID'])
      ->addValue('check_number', $registration['Check_Number'])
      ->addValue('source', $source)
      ->execute()->first();

    // update the default line item from Contribution.create
    \CRM_Core_DAO::executeQuery(<<<SQL
      UPDATE `civicrm_line_item`
      SET
        `label` = '{$this->registrationFeePriceFieldValue['label']}',
        `price_field_id` = {$this->registrationFeePriceFieldValue['price_field_id']},
        `price_field_value_id` = {$this->registrationFeePriceFieldValue['id']}
      WHERE `contribution_id` = {$contribution['id']}
      SQL);

    return $contribution['id'];
  }

  protected function addSeatFee($participantId, $eventId, $contributionId, $seatFeeAmount) {
    $priceSetForEventId = \Civi\Api4\PriceSetEntity::get(FALSE)
      ->addSelect('price_set_id')
      ->addWhere('entity_table', '=', 'civicrm_event')
      ->addWhere('entity_id', '=', $eventId)
      ->execute()->first()['price_set_id'] ?? NULL;

    if ($priceSetForEventId) {
      $priceOptions = \Civi\Api4\PriceFieldValue::get(FALSE)
        ->addWhere('price_field_id.price_set_id', '=', $priceSetForEventId)
        ->addSelect(
          // price field value fields
          'id',
          'price_field_id',
          'label'
        )
        ->execute()->first();

      $params = [
        'entity_id' => $participantId,
        'entity_table' => 'civicrm_participant',
        'contribution_id' => $contributionId,
        'participant_count' => 1,
        // from getEventFees
        'price_field_value_id' => $priceOptions['id'],
        'price_field_id' => $priceOptions['price_field_id'],
        'qty' => 1,
        'unit_price' => $seatFeeAmount,
        'line_total' => $seatFeeAmount,
        'financial_type_id' => 4,
        'label' => "CILB Candidate Registration - {$priceOptions['label']}",
      ];
      \CRM_Price_BAO_LineItem::create($params);

      \Civi\Api4\Participant::update(FALSE)
        ->addWhere('id', '=', $participantId)
        ->addValue('participant_fee_amount', $seatFeeAmount)
        ->addValue('participant_fee_level', $priceOptions['label'])
        ->execute();

      $newTotal = array_sum(\Civi\Api4\LineItem::get(FALSE)
        ->addSelect('line_total')
        ->addWhere('contribution_id', '=', $contributionId)
        ->execute()
        ->column('line_total'));

      // update contribution total
      \CRM_Core_DAO::executeQuery(<<<SQL
        UPDATE `civicrm_contribution`
        SET
          `total_amount` = {$newTotal},
          `net_amount` = {$newTotal}
        WHERE `id` = {$contributionId}
      SQL);

      // Update the financial trxn as well.
      \CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution c
        INNER JOIN civicrm_entity_financial_trxn eft ON eft.entity_id = c.id AND eft.entity_table = 'civicrm_contribution'
        INNER JOIN civicrm_financial_trxn trxn ON trxn.id = eft.financial_trxn_id
        SET trxn.total_amount = %1, trxn.net_amount = %1
        WHERE c.id = %2", [1 => [$newTotal, 'Float'], 2 => [$contributionId, 'Integer']]);
    }

  }

}
