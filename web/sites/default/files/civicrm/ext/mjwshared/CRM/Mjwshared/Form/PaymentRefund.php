<?php

use Civi\Api4\Contribution;
use Civi\Api4\LineItem;
use Civi\Api4\Membership;
use Civi\Api4\Participant;
use Civi\Api4\Payment;
use Civi\Api4\PaymentProcessor;
use Civi\Payment\Exception\PaymentProcessorException;
use CRM_Mjwshared_ExtensionUtil as E;
use Brick\Money\Money;
use Brick\Money\Context\DefaultContext;
use Brick\Math\RoundingMode;


/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Mjwshared_Form_PaymentRefund extends CRM_Core_Form {

  /**
   * @var int $paymentID
   */
  private $paymentID;

  /**
   * @var int $contributionID
   */
  private $contributionID;

  /**
   * @var array $financialTrxn
   */
  private $financialTrxn;

  /**
   * The total amount paid on the Contribution
   *
   * @var float
   */
  private $paidAmount;

  public function buildQuickForm() {
    if (!CRM_Core_Permission::check('refund contributions')) {
      CRM_Core_Error::statusBounce(E::ts('You do not have permission to issue refunds.'));
    }

    $this->addFormRule(['CRM_Mjwshared_Form_PaymentRefund', 'formRule'], $this);

    $this->setTitle('Refund payment');

    $this->paymentID = CRM_Utils_Request::retrieveValue('payment_id', 'Positive', NULL, FALSE, 'REQUEST');
    if ($this->paymentID) {
      $financialTrxns = Payment::get(FALSE)
        ->addWhere('id', '=', $this->paymentID)
        ->execute();
    }
    if (empty($financialTrxns)) {
      $this->contributionID = CRM_Utils_Request::retrieveValue('contribution_id', 'Positive', NULL, FALSE, 'REQUEST');
      if ($this->contributionID) {
        // We can't use API4 Payment::get with contribution_id until https://github.com/civicrm/civicrm-core/pull/33695
        //   is merged because it crashes otherwise
        // $financialTrxns = Payment::get(FALSE)
        //  ->addWhere('contribution_id', '=', $this->contributionID)
        //  ->addWhere('status_id:name', '=', 'Completed')
        // ->execute();
        // Uncomment above and remove below once merged:
        $contribution = civicrm_api3('Mjwpayment', 'get_contribution', [
          'contribution_id' => $this->contributionID,
          'status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_DAO_Contribution', 'contribution_status_id', 'Completed'),
        ]);
        $financialTrxnsAPI3 = $contribution['values'][$contribution['id']]['payments'] ?? NULL;
        $financialTrxns = Payment::get(FALSE)
          ->addWhere('id', 'IN', array_keys($financialTrxnsAPI3) ?? [])
          ->execute();
        // Remove above until here once merged!
        if ($financialTrxns->count() > 1) {
          CRM_Core_Error::statusBounce(E::ts('There is more than one payment for this Contribution. You need to select a specific payment to refund.'));
        }
      }
    }
    if ($financialTrxns->count() === 0) {
      CRM_Core_Error::statusBounce(E::ts('No Payment found. Make sure you specified a valid Payment or Contribution.'));
    }
    $financialTrxn = $financialTrxns->first();
    $this->paymentID = $financialTrxn['id'];
    $this->contributionID = $financialTrxn['contribution_id'];

    if ($financialTrxn['contribution_id'] !== $this->contributionID) {
      CRM_Core_Error::statusBounce('Contribution / Payment does not match');
    }
    $financialTrxn['order_reference'] = $financialTrxn['order_reference'] ?? NULL;

    $paymentProcessor = PaymentProcessor::get(FALSE)
      ->addWhere('id', '=', $financialTrxn['payment_processor_id'])
      ->execute()
      ->first();

    $this->paidAmount = Contribution::get(FALSE)
      ->addSelect('paid_amount')
      ->addWhere('id', '=', $this->contributionID)
      ->execute()
      ->first()['paid_amount'];

    $paymentDisplayInfo = [
      'total_amount' => $financialTrxn['total_amount'],
      'paid_amount' => $this->paidAmount,
      'currency' => $financialTrxn['currency'],
      'trxn_date' => $financialTrxn['trxn_date'],
      'trxn_id' => $financialTrxn['trxn_id'],
      'order_reference' => $financialTrxn['order_reference'],
      'payment_processor_title' => $paymentProcessor['title'] ?? $paymentProcessor['name'],
    ];
    $this->assign('paymentInfo', $paymentDisplayInfo);
    $this->financialTrxn = $financialTrxn;

    $this->add('hidden', 'payment_id');
    $this->add('hidden', 'contribution_id');

    $participantIDs = $membershipIDs = [];

    $lineItems = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $this->contributionID)
      ->execute();
    foreach ($lineItems as $lineItemDetails) {
      switch ($lineItemDetails['entity_table']) {
        case 'civicrm_participant':
          $participantIDs[] = $lineItemDetails['entity_id'];
          break;

        case 'civicrm_membership':
          $membershipIDs[] = $lineItemDetails['entity_id'];
          break;
      }
    }
    if (!empty($participantIDs)) {
      $participantsForAssign = [];
      $this->set('participant_ids', $participantIDs);
      $participants = Participant::get(FALSE)
        ->addSelect('*', 'event_id.title', 'status_id:label', 'contact_id.display_name')
        ->addWhere('id', 'IN', $participantIDs)
        ->execute();
      foreach ($participants->getArrayCopy() as $participant) {
        $participant['status'] = $participant['status_id:label'];
        $participant['event_title'] = $participant['event_id.title'];
        $participant['display_name'] = $participant['contact_id.display_name'];
        $participantsForAssign[] = $participant;
      }
      $this->addYesNo('cancel_participants', E::ts('Do you want to cancel these registrations when you refund the payment?'), NULL, TRUE);
    }
    $this->assign('participants', $participantsForAssign ?? NULL);

    if (!empty($membershipIDs)) {
      $membershipsForAssign = [];
      $this->set('membership_ids', $membershipIDs);
      $memberships = Membership::get(FALSE)
        ->addSelect('*', 'membership_type_id:label', 'status_id:label', 'contact_id.display_name')
        ->addWhere('id', 'IN', $membershipIDs)
        ->execute();
      foreach ($memberships->getArrayCopy() as $membership) {
        $membership['status'] = $membership['status_id:label'];
        $membership['type'] = $membership['membership_type_id:label'];
        $membership['display_name'] = $membership['contact_id.display_name'];
        $membershipsForAssign[] = $membership;
      }
      $this->addYesNo('cancel_memberships', E::ts('Do you want to cancel these memberships when you refund the payment?'), NULL, TRUE);
    }
    $this->assign('memberships', $membershipsForAssign ?? NULL);

    $this->addMoney('refund_amount',
      E::ts('Amount to Refund'),
      TRUE,
      [],
      TRUE, 'currency', $financialTrxn['currency'], TRUE
    );

    $this->addButtons([
      [
        'type' => 'submit',
        'icon' => 'fa-money-check-dollar',
        'name' => E::ts('Process Refund'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ],
    ]);
  }

  public function setDefaultValues() {
    if ($this->paymentID) {
      $defaults['payment_id'] = $this->paymentID;
      $this->set('payment_id', $this->paymentID);
      $defaults['contribution_id'] = $this->contributionID;
      $this->set('contribution_id', $this->contributionID);
      // If we have one payment and one refund the paid amount will be less.
      // Ideally we match the refund to the original payment but this is a simpler way that will work
      // in most cases. It is modifiable by the user and the paymentprocessor won't usually allow
      // refunding more than the original amount so it should be fine.
      $defaults['refund_amount'] = min($this->paidAmount, $this->financialTrxn['total_amount']);
    }

    return $defaults ?? [];
  }

  /**
   * Global form rule.
   *
   * @param array $fields
   *   The input form values.
   * @param array $files
   *   The uploaded files if any.
   * @param CRM_Core_Form $form
   *
   * @return bool|array
   *   true if no errors, else array of errors
   */
  public static function formRule($fields, $files, $form) {
    $errors = [];

    $formValues = $form->getSubmitValues();
    $paymentID = $form->get('payment_id');

    $payment = Payment::get(FALSE)
      ->addWhere('id', '=', $paymentID)
      ->execute()
      ->first();

    // Check refund amount
    $refundAmount = Money::of($formValues['refund_amount'], $payment['currency'], new DefaultContext(), RoundingMode::CEILING);
    $paymentAmount = Money::of($payment['total_amount'], $payment['currency'], new DefaultContext(), RoundingMode::CEILING);

    if ($refundAmount->isGreaterThan($paymentAmount)) {
      $errors['refund_amount'] = 'Cannot refund more than the original amount';
    }
    if ($refundAmount->isNegativeOrZero()) {
      $errors['refund_amount'] = 'Cannot refund zero or negative amount';
    }

    return $errors;
  }

  public function postProcess() {
    $formValues = $this->getSubmitValues();
    $paymentID = $this->get('payment_id');
    $participantIDs = ($formValues['cancel_participants'] ?? FALSE) ? $this->get('participant_ids') : [];
    $membershipIDs = ($formValues['cancel_memberships'] ?? FALSE) ? $this->get('membership_ids') : [];

    try {
      $result = \Civi\Api4\PaymentMJW::refund(FALSE)
        ->setPaymentID($paymentID)
        ->setRefundAmount($formValues['refund_amount'])
        ->setParticipantIDs($participantIDs)
        ->setMembershipIDs($membershipIDs)
        ->execute();
    }
    catch (\Throwable $e) {
      CRM_Core_Error::statusBounce($e->getMessage());
    }
    CRM_Core_Session::setStatus($result['message'] ?? '', 'Refund processed', 'success');
  }

}
