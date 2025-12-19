<?php

namespace Civi\Api4\Action\PaymentMJW;

use Civi\Api4\Generic\AbstractCreateAction;
use Civi\Api4\Membership;
use Civi\Api4\Participant;
use Civi\Api4\Payment;
use CRM_Mjwshared_ExtensionUtil as E;
use Brick\Money\Money;
use Brick\Money\Context\DefaultContext;
use Brick\Math\RoundingMode;

/**
 * This API Action refunds one or more payments.
 *
 */
class Refund extends AbstractCreateAction {

  /**
   * The Payment (FinancialTrxn) ID to refund
   *
   * @var int
   * @required
   */
  protected int $paymentID;

  /**
   * The amount to refund
   *
   * @var float
   * @required
   */
  protected float $refundAmount;

  /**
   * List of Participant IDs to cancel
   * @var array
   */
  protected array $participantIDs = [];

  /**
   * List of Membership IDs to cancel
   * @var array
   */
  protected array $membershipIDs = [];

  public function getEntityName() {
    return 'Payment';
  }

  /**
   *
   * Note that the result class is that of the annotation below, not the h
   * in the method (which must match the parent class)
   *
   * @var \Civi\Api4\Generic\Result $result
   */
  public function _run(\Civi\Api4\Generic\Result $result) {
    try {
      $payment = Payment::get(FALSE)
        ->addWhere('id', '=', $this->paymentID)
        ->execute()
        ->first();

      // Check refund amount
      $refundAmount = Money::of($this->refundAmount, $payment['currency'], new DefaultContext(), RoundingMode::CEILING);
      $paymentAmount = Money::of($payment['total_amount'], $payment['currency'], new DefaultContext(), RoundingMode::CEILING);

      if ($refundAmount->isGreaterThan($paymentAmount)) {
        throw new \CRM_Core_Exception('Cannot refund more than the original amount');
      }
      if ($refundAmount->isNegativeOrZero()) {
        throw new \CRM_Core_Exception('Cannot refund zero or negative amount');
      }

      $refundParams = [
        'payment_processor_id' => $payment['payment_processor_id'],
        'amount' => $refundAmount->getAmount()->toFloat(),
        'currency' => $payment['currency'],
        'trxn_id' => $payment['trxn_id'],
      ];
      if (empty($refundParams['payment_processor_id'])) {
        // Manual payment, no paymentprocessor.
        $refund = ['refund_status' => 'Completed'];
      }
      else {
        // Request and process refund using payment processor.
        // @todo: Switch to API4 once https://github.com/civicrm/civicrm-core/pull/33694 is merged
        $refund = reset(civicrm_api3('PaymentProcessor', 'Refund', $refundParams)['values']);
      }
      if ($refund['refund_status'] === 'Completed') {
        $refundPaymentParams = [
          'contribution_id' => $payment['contribution_id'],
          'trxn_id' => $refund['refund_trxn_id'],
          'order_reference' => $payment['order_reference'] ?? NULL,
          'total_amount' => 0 - abs($refundAmount->getAmount()->toFloat()),
          'fee_amount' => 0 - abs($refund['fee_amount']),
          'payment_processor_id' => $payment['payment_processor_id'],
        ];

        $lock = \Civi::lockManager()->acquire('data.contribute.contribution.' . $refundPaymentParams['contribution_id']);
        if (!$lock->isAcquired()) {
          throw new \CRM_Core_Exception('Could not acquire lock to record refund for contribution: ' . $refundPaymentParams['contribution_id']);
        }
        $refundPayments = Payment::get(FALSE)
          ->addWhere('contribution_id', '=', $refundPaymentParams['contribution_id'])
          ->addWhere('total_amount', '=', $refundPaymentParams['total_amount'])
          ->addWhere('trxn_id', '=', $refundPaymentParams['trxn_id'])
          ->execute();
        if ($refundPayments->count() === 0) {
          // Record the refund in CiviCRM
          Payment::create(FALSE)
            ->setValues($refundPaymentParams)
            ->execute();
        }
        $lock->release();
        $message = E::ts('Refund was processed successfully.');

        if (!empty($this->participantIDs)) {
          Participant::update(FALSE)
            ->addValue('status_id.name', 'Cancelled')
            ->addWhere('id', 'IN', $this->participantIDs)
            ->execute();
          $message .= ' ' . E::ts('Cancelled %1 participant registration(s).', [1 => count($this->participantIDs)]);
        }

        if (!empty($this->membershipIDs)) {
          Membership::update(FALSE)
            ->addValue('status_id.name', 'Cancelled')
            ->addWhere('id', 'IN', $this->membershipIDs)
            ->execute();
          $message .= ' ' . E::ts('Cancelled %1 membership(s).', [1 => count($this->membershipIDs)]);
        }
      }
      else {
        throw new \CRM_Core_Exception("Refund status '{$refund['refund_status']}'is not supported at this time and was not recorded in CiviCRM.");
      }
    } catch (\Throwable $e) {
      throw new \CRM_Core_Exception($e->getMessage(), NULL, 'Refund failed');
    }

    $result->exchangeArray(['message' => $message ?? '']);
    return $result;
  }

}
