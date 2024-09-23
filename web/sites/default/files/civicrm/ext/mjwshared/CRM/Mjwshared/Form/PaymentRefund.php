<?php

use Civi\Api4\LineItem;
use Civi\Api4\Membership;
use Civi\Api4\Participant;
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

  public function buildQuickForm() {
    if (!CRM_Core_Permission::check('edit contributions')) {
      CRM_Core_Error::statusBounce(ts('You do not have permission to access this page.'));
    }

    $this->addFormRule(['CRM_Mjwshared_Form_PaymentRefund', 'formRule'], $this);

    $this->setTitle('Refund payment');

    $this->paymentID = CRM_Utils_Request::retrieveValue('payment_id', 'Positive', NULL, FALSE, 'REQUEST');
    if (!$this->paymentID) {
      CRM_Core_Error::statusBounce('Payment not found!');
    }

    $this->contributionID = CRM_Utils_Request::retrieveValue('contribution_id', 'Positive', NULL, FALSE, 'REQUEST');
    if (!$this->contributionID) {
      CRM_Core_Error::statusBounce('Contribution not found!');
    }

    $financialTrxn = reset(civicrm_api3('Mjwpayment', 'get_payment', [
      'financial_trxn_id' => $this->paymentID,
    ])['values']);
    if ((int)$financialTrxn['contribution_id'] !== $this->contributionID) {
      CRM_Core_Error::statusBounce('Contribution / Payment does not match');
    }
    $financialTrxn['order_reference'] = $financialTrxn['order_reference'] ?? NULL;

    $paymentProcessor = PaymentProcessor::get(FALSE)
      ->addWhere('id', '=', $financialTrxn['payment_processor_id'])
      ->execute()
      ->first();
    $financialTrxn['payment_processor_title'] = $paymentProcessor['title'] ?? $paymentProcessor['name'];

    $this->assign('paymentInfo', $financialTrxn);
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
      $participants = Participant::get()
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
      ts('Refund Amount'),
      TRUE,
      [],
      TRUE, 'currency', $financialTrxn['currency'], TRUE
    );

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Refund'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);
  }

  public function setDefaultValues() {
    if ($this->paymentID) {
      $this->_defaults['payment_id'] = $this->paymentID;
      $this->set('payment_id', $this->paymentID);
      $this->_defaults['contribution_id'] = $this->contributionID;
      $this->set('contribution_id', $this->contributionID);
      $this->_defaults['refund_amount'] = $this->financialTrxn['total_amount'];
    }
    return $this->_defaults;
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

    $payment = reset(civicrm_api3('Mjwpayment', 'get_payment', ['id' => $paymentID])['values']);

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
    $participantIDs = $this->get('participant_ids');
    $cancelParticipants = $formValues['cancel_participants'] ?? FALSE;
    $membershipIDs = $this->get('membership_ids');
    $cancelMemberships = $formValues['cancel_memberships'] ?? FALSE;

    try {
      $payment = reset(civicrm_api3('Mjwpayment', 'get_payment', ['id' => $paymentID])['values']);

      // Check refund amount
      $refundAmount = Money::of($formValues['refund_amount'], $payment['currency'], new DefaultContext(), RoundingMode::CEILING);
      $paymentAmount = Money::of($payment['total_amount'], $payment['currency'], new DefaultContext(), RoundingMode::CEILING);

      if ($refundAmount->isGreaterThan($paymentAmount)) {
        throw new PaymentProcessorException('Cannot refund more than the original amount');
      }
      if ($refundAmount->isNegativeOrZero()) {
        throw new PaymentProcessorException('Cannot refund zero or negative amount');
      }

      $refundParams = [
        'payment_processor_id' => $payment['payment_processor_id'],
        'amount' => $refundAmount->getAmount()->toFloat(),
        'currency' => $payment['currency'],
        'trxn_id' => $payment['trxn_id'],
      ];
      $refund = reset(civicrm_api3('PaymentProcessor', 'Refund', $refundParams)['values']);
      if ($refund['refund_status'] === 'Completed') {
        $refundPaymentParams = [
          'contribution_id' => $payment['contribution_id'],
          'trxn_id' => $refund['refund_trxn_id'],
          'order_reference' => $payment['order_reference'] ?? NULL,
          'total_amount' => 0 - abs($refundAmount->getAmount()->toFloat()),
          'fee_amount' => 0 - abs($refund['fee_amount']),
          'payment_processor_id' => $payment['payment_processor_id'],
        ];

        $lock = Civi::lockManager()->acquire('data.contribute.contribution.' . $refundPaymentParams['contribution_id']);
        if (!$lock->isAcquired()) {
          throw new PaymentProcessorException('Could not acquire lock to record refund for contribution: ' . $refundPaymentParams['contribution_id']);
        }
        $refundPayment = civicrm_api3('Payment', 'get', [
          'contribution_id' => $refundPaymentParams['contribution_id'],
          'total_amount' => $refundPaymentParams['total_amount'],
          'trxn_id' => $refundPaymentParams['trxn_id'],
        ]);
        if (empty($refundPayment['count'])) {
          // Record the refund in CiviCRM
          civicrm_api3('Mjwpayment', 'create_payment', $refundPaymentParams);
        }
        $lock->release();
        $message = E::ts('Refund was processed successfully.');

        if ($cancelParticipants && !empty($participantIDs)) {
          foreach ($participantIDs as $participantID) {
            civicrm_api3('Participant', 'create', [
              'id' => $participantID,
              'status_id' => 'Cancelled',
            ]);
          }
          $message .= ' ' . E::ts('Cancelled %1 participant registration(s).', [1 => count($participantIDs)]);
        }

        if ($cancelMemberships && !empty($membershipIDs)) {
          Membership::update(FALSE)
            ->addValue('status_id.name', 'Cancelled')
            ->addWhere('id', 'IN', $membershipIDs)
            ->execute();
          $message .= ' ' . E::ts('Cancelled %1 membership(s).', [1 => count($membershipIDs)]);
        }

        CRM_Core_Session::setStatus($message, 'Refund processed', 'success');
      }
      else {
        CRM_Core_Error::statusBounce("Refund status '{$refund['refund_status']}'is not supported at this time and was not recorded in CiviCRM.");
      }
    } catch (Exception $e) {
      CRM_Core_Error::statusBounce($e->getMessage(), NULL, 'Refund failed');
    }
  }

}
