<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use Civi\Api4\Contribution;
use Civi\Payment\Exception\PaymentProcessorException;

/**
 * Shared payment IPN functions that should one day be migrated to CiviCRM core
 *
 * Trait CRM_Core_Payment_MJWIPNTrait
 */
trait CRM_Core_Payment_MJWIPNTrait {

  /**
   * @var \CRM_Core_Payment Payment processor
   */
  protected $_paymentProcessor;

  /**
   * Do we send an email receipt for each contribution?
   *
   * @var int|null
   */
  protected $is_email_receipt = NULL;

  /**
   * The recurring contribution ID associated with the transaction
   * @var int
   */
  protected $contribution_recur_id = NULL;

  /**
   * The recurring contribution detail (as retrieved by API4)
   * @var array
   */
  protected $contributionRecur = [];

  /**
   *  The IPN event type
   * @var string
   */
  protected $eventType = NULL;

  /**
   * The IPN event ID
   * @var string
   */
  protected $eventID = NULL;

  /**
   * Exit on exceptions (TRUE), or just throw them (FALSE).
   *
   * @var bool
   */
  protected $exitOnException = TRUE;

  /**
   * Should we verify (re-retrieve) the object that we have been given.
   * Usually TRUE when an IPN request is received.
   *
   * @var bool
   */
  protected $verifyData = TRUE;

  /**
   * The data provided by the IPN
   *
   * @var array|Object|string
   */
  protected $data;

  /**
   *
   * Set the value of is_email_receipt to use when a new contribution is
   * received for a recurring contribution. If set to NULL, we use CiviCRM core
   * logic to determine if a receipt should be sent (typically the recurring
   * contribution setting).
   *
   * @param int|null|bool $sendReceipt The value of is_email_receipt
   */
  public function setSendEmailReceipt($sendReceipt) {
    if (is_null($sendReceipt)) {
      // Let CiviCRM core decide whether to send a receipt.
      $this->is_email_receipt = NULL;
    }
    elseif ($sendReceipt) {
      // Explicitly turn on receipts.
      $this->is_email_receipt = 1;
    }
    else {
      // Explicitly turn off receipts.
      $this->is_email_receipt = 0;
    }
  }

  /**
   * Get the value of is_email_receipt to use when a new contribution is received.
   * If NULL we let CiviCRM core decide whether to send a receipt.
   *
   * See setSendEmalReceipt() for more details.
   *
   * @return int|null
   */
  public function getSendEmailReceipt() {
    return $this->is_email_receipt;
  }

  /**
   * Set and initialise the paymentProcessor object
   * @param int $paymentProcessorID
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function setPaymentProcessor($paymentProcessorID) {
    try {
      $this->_paymentProcessor = \Civi\Payment\System::singleton()->getById($paymentProcessorID);
    }
    catch (Exception $e) {
      $this->exception('Failed to get payment processor');
    }
  }

  /**
   * Get the payment processor object
   */
  public function getPaymentProcessor() {
    return $this->_paymentProcessor;
  }

  /**
   * @param int $recurID
   *
   * @return void
   */
  public function setContributionRecurID(int $recurID) {
    $this->contribution_recur_id = $recurID;
  }

  /**
   * @return int|null
   */
  public function getContributionRecurID() {
    return $this->contribution_recur_id;
  }

  /**
   * @param bool $verify
   */
  public function setVerifyData($verify) {
    $this->verifyData = $verify;
  }

  /**
   * @return bool
   */
  public function getVerifyData() {
    return $this->verifyData;
  }

  /**
   * @param string $eventID
   */
  public function setEventID($eventID) {
    $this->eventID = $eventID;
  }

  /**
   * @return string
   */
  public function getEventID() {
    return $this->eventID;
  }

  /**
   * Set the eventType. Returns TRUE if event is supported. FALSE otherwise.
   * @param string $eventType
   *
   * @return bool
   */
  public function setEventType($eventType) {
    $this->eventType = $eventType;
    return TRUE;
  }

  /**
   * @return string
   */
  public function getEventType() {
    return $this->eventType;
  }

  /**
   * @param array|Object|string $data
   */
  public function setData($data) {
    $this->data = $data;
  }

  /**
   * @return array|Object
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Check that required params are present
   *
   * @param string $description
   *   For error logs
   * @param array $requiredParams
   *   Array of params that are required
   * @param array $params
   *   Array of params to check
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  protected function checkRequiredParams($description, $requiredParams, $params) {
    foreach ($requiredParams as $required) {
      if (!isset($params[$required])) {
        $this->exception("{$description}: Missing mandatory parameter: {$required}");
      }
    }
  }

  /**
   * Cancel a subscription (recurring contribution)
   * @param array $params
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  protected function updateRecurCancelled($params) {
    $this->checkRequiredParams('updateRecurCancelled', ['id'], $params);
    civicrm_api3('ContributionRecur', 'cancel', $params);
  }

  /**
   * Update the subscription (recurring contribution) to a successful status
   * @param array $params
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  private function updateRecurSuccess($params) {
    $this->checkRequiredParams('updateRecurSuccess', ['id'], $params);
    $params['failure_count'] = 0;
    $params['contribution_status_id'] = 'In Progress';

    // Successful charge & more to come.
    civicrm_api3('ContributionRecur', 'create', $params);
  }

  /**
   * Update the subscription (recurring contribution) to a completed status
   * @param array $params
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  private function updateRecurCompleted($params) {
    $this->checkRequiredParams('updateRecurCompleted', ['id'], $params);

    \Civi\Api4\ContributionRecur::update(FALSE)
      ->addWhere('id', '=', $params['id'])
      ->addValue('contribution_status_id:name', 'Completed')
      ->execute();
  }

  /**
   * Update the subscription (recurring contribution) to a failing status
   * @param array $params
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  private function updateRecurFailed($params) {
    $this->checkRequiredParams('updateRecurFailed', ['id'], $params);

    $failureCount = civicrm_api3('ContributionRecur', 'getvalue', [
      'id' => $params['id'],
      'return' => 'failure_count',
    ]);
    $failureCount++;

    $params['failure_count'] = $failureCount;
    $params['contribution_status_id'] = 'Failed';

    // Change the status of the Recurring and update failed attempts.
    civicrm_api3('ContributionRecur', 'create', $params);
  }

  /**
   * Repeat a contribution (call the Contribution.repeattransaction API)
   *
   * @param array $repeatContributionParams
   *
   * @return int The new Contribution ID
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  private function repeatContribution(array $repeatContributionParams): int {
    $this->checkRequiredParams(
      'repeatContribution',
      [
        'contribution_status_id',
        'contribution_recur_id',
        // Optional: 'original_contribution_id',
        'receive_date',
        'order_reference',
        'trxn_id',
        //'total_amount',
      ],
      $repeatContributionParams
    );
    // Optional Params: fee_amount

    // Creat contributionParams for Contribution.repeattransaction and set some values
    $contributionParams = $repeatContributionParams;
    // Status should be pending if we have a successful payment
    switch ($contributionParams['contribution_status_id']) {
      case CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'):
        // Create a contribution in Pending state using Contribution.repeattransaction and then complete using Payment.create
        $contributionParams['contribution_status_id'] = 'Pending';
        $createPayment = TRUE;
        break;

      default:
        // Failed etc.
        // For any other status create it directly using Contribution.repeattransaction and don't create a payment
        $createPayment = FALSE;
    }

    // Create the new contribution
    $contributionParams['trxn_id'] = $repeatContributionParams['order_reference'];
    // We send a receipt when adding a payment, not now
    $contributionParams['is_email_receipt'] = FALSE;
    try {
      $contribution = reset(civicrm_api3('Contribution', 'repeattransaction', $contributionParams)['values']);
    }
    catch (Exception $e) {
      // We catch, log, throw again so we have debug details in the logs
      $message = 'MJWIPNTrait call to repeattransaction failed: ' . $e->getMessage() . '; params: ' . print_r($contributionParams, TRUE);
      \Civi::log()->error($message);
      throw new PaymentProcessorException($message);
    }

    // Get total amount from contribution returned by repeatTransaction (Which came from the recur template Contribution)
    $repeatContributionParams['total_amount'] = $repeatContributionParams['total_amount'] ?? $contribution['total_amount'];

    if ($createPayment) {
      // Start by passing through all params (we may pass in customdata as well in API4 format)
      $paymentParams = $repeatContributionParams;
      // Now map contribution keys to payment keys
      $paymentParamsMap = [
        'receive_date' => 'trxn_date',
        'order_reference' => 'order_reference',
        'trxn_id' => 'trxn_id',
        'total_amount' => 'total_amount',
        'fee_amount' => 'fee_amount',
      ];
      foreach ($paymentParamsMap as $contributionKey => $paymentKey) {
        if (isset($contributionParams[$contributionKey])) {
          unset($paymentParams[$contributionKey]);
          $paymentParams[$paymentKey] = $contributionParams[$contributionKey];
        }
      }
      $paymentParams['contribution_id'] = $contribution['id'];
      $paymentParams['payment_processor_id'] = $this->getPaymentProcessor()->getID();
      $paymentParams['is_send_contribution_notification'] = $this->getSendEmailReceipt();
      $paymentParams['skipCleanMoney'] = TRUE;
      civicrm_api3('Mjwpayment', 'create_payment', $paymentParams);
    }
    return $contribution['id'];
  }

  /**
   * @param array $params
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   *
   * @deprecated Was used in Stripe until 6.11
   */
  private function updateContribution($params) {
    $this->checkRequiredParams('updateContribution', ['contribution_id'], $params);
    $params['id'] = $params['contribution_id'];
    unset($params['contribution_id']);
    $params['skipCleanMoney'] = TRUE;
    $params['skipRecentView'] = TRUE;
    $params['skipLineItem'] = TRUE;
    $params['is_post_payment_create'] = TRUE;
    civicrm_api3('Contribution', 'create', $params);
  }

  /**
   * Complete a pending contribution and update associated entities (recur/membership)
   *
   * @param array $contributionParams
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  private function updateContributionCompleted(array $contributionParams) {
    $this->checkRequiredParams('updateContributionCompleted', ['contribution_id', 'trxn_date', 'order_reference', 'trxn_id', 'total_amount'], $contributionParams);

    // Start by passing through all params (we may pass in customdata as well in API4 format)
    $paymentParams = $contributionParams;
    // Now map contribution keys to payment keys
    $paymentParamsMap = [
      'contribution_id' => 'contribution_id',
      'trxn_date' => 'trxn_date',
      'order_reference' => 'order_reference',
      'trxn_id' => 'trxn_id',
      'total_amount' => 'total_amount',
      'fee_amount' => 'fee_amount',
    ];
    foreach ($paymentParamsMap as $contributionKey => $paymentKey) {
      if (isset($contributionParams[$contributionKey])) {
        unset($paymentParams[$contributionKey]);
        $paymentParams[$paymentKey] = $contributionParams[$contributionKey];
      }
    }

    // CiviCRM does not (currently) allow changing contribution_status=Failed to anything else.
    // But we need to go from Failed to Completed if payment succeeds following a failure.
    // So we check for Failed status and update to Pending so it will transition to Completed.
    // Fixed in 5.29 with https://github.com/civicrm/civicrm-core/pull/17943
    // Also set cancel_date to NULL because we are setting it for failed payments and UI will still display "greyed
    // out" if it is set.
    $failedContributionStatus = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Failed');
    if (isset($contributionParams['contribution_status_id']) && ((int) $contributionParams['contribution_status_id'] === $failedContributionStatus)) {
      $sql = "UPDATE civicrm_contribution SET contribution_status_id=%1,cancel_date=NULL WHERE id=%2";
      $queryParams = [
        1 => [CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending'), 'Positive'],
        2 => [$contributionParams['contribution_id'], 'Positive'],
      ];
      CRM_Core_DAO::executeQuery($sql, $queryParams);
    }
    $paymentParams['is_send_contribution_notification'] = $this->getSendEmailReceipt();
    $paymentParams['skipCleanMoney'] = TRUE;
    $paymentParams['payment_processor_id'] = $this->getPaymentProcessor()->getID();
    civicrm_api3('Mjwpayment', 'create_payment', $paymentParams);
  }

  /**
   * Update a contribution to failed
   *
   * @param array $params ['contribution_id', 'order_reference'{, cancel_date, cancel_reason}]
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  private function updateContributionFailed($params) {
    $this->checkRequiredParams('updateContributionFailed', ['contribution_id', 'order_reference'], $params);
    Contribution::update(FALSE)
      ->addValue('contribution_status_id:name', 'Failed')
      ->addValue('trxn_id', $params['order_reference'])
      ->addValue('cancel_date', $params['cancel_date'] ?? '')
      ->addValue('cancel_reason', $params['cancel_reason'] ?? '')
      ->addWhere('id', '=', $params['contribution_id'])
      ->execute();
    // No financial_trxn is created so we don't need to update that.
  }

  /**
   * Record a refund on a contribution
   * This wraps around the payment.create API to support earlier releases than features were available
   *
   * Examples:
   * $result = civicrm_api3('Payment', 'create', [
   *   'contribution_id' => 590,
   *   'total_amount' => -3,
   *   'trxn_date' => 20191105200300,
   *   'trxn_result_code' => "Test a refund with fees",
   *   'fee_amount' => -0.25,
   *   'trxn_id' => "abctx123",
   *   'order_reference' => "abcor123",
   * ]);
   *
   *  Returns:
   * "is_error": 0,
   * "version": 3,
   * "count": 1,
   * "id": 465,
   * "values": {
   *   "465": {
   *     "id": "465",
   *     "from_financial_account_id": "7",
   *     "to_financial_account_id": "6",
   *     "trxn_date": "20191105200300",
   *     "total_amount": "-3",
   *     "fee_amount": "-0.25",
   *     "net_amount": "",
   *     "currency": "USD",
   *     "is_payment": "1",
   *     "trxn_id": "abctx123",
   *     "trxn_result_code": "Test a refund with fees",
   *     "status_id": "7",
   *     "payment_processor_id": ""
   *   }
   * }
   *
   * @param array $params
   *
   * @throws \CRM_Core_Exception
   */
  protected function updateContributionRefund($params) {
    $this->checkRequiredParams('updateContributionRefund', ['contribution_id', 'total_amount'], $params);
    $params['payment_processor_id'] = $this->getPaymentProcessor()->getID();

    $lock = Civi::lockManager()->acquire('data.contribute.contribution.' . $params['contribution_id']);
    if (!$lock->isAcquired()) {
      throw new PaymentProcessorException('Could not acquire lock to record refund for contribution: ' . $params['contribution_id']);
    }
    // Check if it was already recorded (in case two processes are running the same time - eg. Refund UI + IPN processing).
    $refundPayment = civicrm_api3('Payment', 'get', [
      'contribution_id' => $params['contribution_id'],
      'total_amount' => $params['total_amount'],
      'trxn_id' => $params['trxn_id'] ?? NULL,
    ]);
    if (empty($refundPayment['count'])) {
      civicrm_api3('Mjwpayment', 'create_payment', $params);
    }
    $lock->release();
  }

  /**
   * Switch between "exit on exception" mode and "regular exception handling".
   *
   * @param bool $exitOnException Switch between:
   * - TRUE (default): Exit with HTTP response code 400 when an exception occurs
   * - FALSE: Just throw the exception regularly
   */
  public function setExceptionMode($exitOnException) {
    $this->exitOnException = $exitOnException;
  }

  /**
   * Log and throw an IPN exception
   *
   * @param string $message
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  protected function exception($message) {
    $label = method_exists($this->getPaymentProcessor(), 'getPaymentProcessorLabel') ? $this->getPaymentProcessor()->getPaymentProcessorLabel() : __CLASS__;
    $errorMessage = $label . ' Exception: Event: ' . $this->eventType . ' Error: ' . $message;
    Civi::log()->error($errorMessage);
    if ($this->exitOnException) {
      http_response_code(400);
      exit(1);
    } else {
      Throw new PaymentProcessorException($message);
    }
  }

}
