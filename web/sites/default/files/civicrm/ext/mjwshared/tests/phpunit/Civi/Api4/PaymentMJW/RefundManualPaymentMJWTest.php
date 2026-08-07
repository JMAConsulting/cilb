<?php

use Civi\Api4\Contribution;
use Civi\Api4\Payment;
use Civi\Api4\PaymentMJW;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

/**
 * Refunding a manual / refund-unsupported payment must record cleanly.
 *
 * A manual payment (no payment processor — e.g. a recorded check) has no
 * external transaction id and no processor fee, so PaymentMJW::refund reaches
 * the record-refund step with $refund = ['refund_status' => 'Completed'] and
 * refund_trxn_id / fee_amount absent. Reading those unguarded emits an
 * undefined-array-key warning — which this suite (convertWarningsToExceptions)
 * turns into a failure — so this is a regression test for that guard.
 *
 * @group headless
 */
class RefundManualPaymentMJWTest extends CiviUnitTestCase implements HeadlessInterface, HookInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function tearDown(): void {
    $this->quickCleanUpFinancialEntities();
    $this->quickCleanup(['civicrm_contact']);
    parent::tearDown();
  }

  public function testRefundManualPaymentRecordsCleanly(): void {
    $contactID = $this->individualCreate();

    $contributionID = (int) Contribution::create(FALSE)
      ->addValue('contact_id', $contactID)
      ->addValue('financial_type_id:name', 'Donation')
      ->addValue('total_amount', 100)
      ->addValue('contribution_status_id:name', 'Pending')
      ->addValue('receive_date', '2026-01-01')
      ->execute()
      ->first()['id'];

    // Record a MANUAL payment (no payment_processor_id) — the check / offline
    // case the guard fix covers. This completes the contribution.
    civicrm_api3('Payment', 'create', [
      'contribution_id' => $contributionID,
      'total_amount' => 100,
      'trxn_date' => '2026-01-02',
      'is_send_contribution_notification' => 0,
    ]);

    $paymentID = (int) Payment::get(FALSE)
      ->addWhere('contribution_id', '=', $contributionID)
      ->addWhere('total_amount', '>', 0)
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first()['id'];

    // Before the guard this raised an undefined-array-key warning (fatal here)
    // on $refund['refund_trxn_id'] / $refund['fee_amount']; it must now record
    // the refund without error.
    PaymentMJW::refund(FALSE)
      ->setPaymentID($paymentID)
      ->setRefundAmount(100)
      ->execute();

    $refund = Payment::get(FALSE)
      ->addSelect('id', 'total_amount', 'trxn_id')
      ->addWhere('contribution_id', '=', $contributionID)
      ->addWhere('total_amount', '<', 0)
      ->execute()
      ->first();

    $this->assertNotEmpty($refund, 'a refund (negative) payment was recorded');
    $this->assertEquals(-100.0, (float) $refund['total_amount']);
    $this->assertEmpty($refund['trxn_id'], 'a manual refund has no external transaction id');
  }

}
