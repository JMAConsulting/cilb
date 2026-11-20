<?php

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Contribution;
use Civi\Api4\Payment;
use Civi\Api4\PaymentMJW;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

/**
 * PaymentMJW::refund against a manual payment (no payment processor — e.g. a
 * recorded check).
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

  /**
   * A manual payment has no external transaction id and no processor fee, so
   * refund reaches the record-refund step with refund_trxn_id / fee_amount
   * absent. Reading those unguarded emits an undefined-array-key warning, which
   * this suite (convertWarningsToExceptions) turns into a failure.
   */
  public function testRefundManualPaymentRecordsCleanly(): void {
    [$contributionID, $paymentID] = $this->createManualPayment();

    PaymentMJW::refund(FALSE)
      ->setPaymentID($paymentID)
      ->setRefundAmount(100)
      ->execute();

    $refund = $this->assertRefundRecorded($contributionID);
    $this->assertEmpty($refund['trxn_id'], 'a manual refund has no external transaction id');
  }

  /**
   * The extension's refund form and contribution link gate on 'refund
   * contributions', so the API they hand off to must accept it too.
   */
  public function testRefundIsAllowedWithRefundContributionsPermission(): void {
    [$contributionID, $paymentID] = $this->createManualPayment();

    $this->setPermissions([
      'access CiviCRM',
      'access CiviContribute',
      'edit contributions',
      'refund contributions',
    ]);

    PaymentMJW::refund(TRUE)
      ->setPaymentID($paymentID)
      ->setRefundAmount(100)
      ->execute();

    $this->assertRefundRecorded($contributionID);
  }

  public function testRefundIsDeniedWithoutRefundContributionsPermission(): void {
    [, $paymentID] = $this->createManualPayment();

    $this->setPermissions([
      'access CiviCRM',
      'access CiviContribute',
      'edit contributions',
    ]);

    $this->expectException(UnauthorizedException::class);

    PaymentMJW::refund(TRUE)
      ->setPaymentID($paymentID)
      ->setRefundAmount(100)
      ->execute();
  }

  /**
   * Create a pending contribution and complete it with a manual payment.
   *
   * @return int[] [contribution ID, payment ID]
   */
  private function createManualPayment(): array {
    $contributionID = (int) Contribution::create(FALSE)
      ->addValue('contact_id', $this->individualCreate())
      ->addValue('financial_type_id:name', 'Donation')
      ->addValue('total_amount', 100)
      ->addValue('contribution_status_id:name', 'Pending')
      ->addValue('receive_date', '2026-01-01')
      ->execute()
      ->first()['id'];

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

    return [$contributionID, $paymentID];
  }

  /**
   * Assert a full refund was recorded against the contribution, and return it.
   */
  private function assertRefundRecorded(int $contributionID): array {
    $refund = Payment::get(FALSE)
      ->addSelect('id', 'total_amount', 'trxn_id')
      ->addWhere('contribution_id', '=', $contributionID)
      ->addWhere('total_amount', '<', 0)
      ->execute()
      ->first();

    $this->assertNotEmpty($refund, 'a refund (negative) payment was recorded');
    $this->assertEquals(-100.0, (float) $refund['total_amount']);
    return $refund;
  }

}
