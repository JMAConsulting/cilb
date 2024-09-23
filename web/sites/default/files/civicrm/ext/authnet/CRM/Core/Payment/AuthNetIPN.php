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

use Civi\Api4\ContributionRecur;
use Civi\Api4\PaymentprocessorWebhook;
use Civi\MJW\Logger;
use Civi\Payment\Exception\PaymentProcessorException;
use CRM_AuthNetEcheck_ExtensionUtil as E;
use \Authnetjson\AuthnetWebhook as AuthnetWebhook;
use \Authnetjson\AuthnetApiFactory as AuthnetApiFactory;

class CRM_Core_Payment_AuthNetIPN {

  use CRM_Core_Payment_MJWIPNTrait;

  /**
   * @var \Civi\MJW\Logger $log
   */
  protected Logger $log;

  /**
   * @var \CRM_Core_Payment_AuthorizeNetCommon Payment processor
   */
  protected $_paymentProcessor;

  /**
   * Authorize.net webhook transaction ID
   *
   * @var string
   */
  private $trxnID = NULL;

  /**
   * Authorize.net webhook subscription ID
   *
   * @var string
   */
  private $subscriptionID = NULL;

  /**
   * Get the transaction ID
   *
   * @return string
   */
  private function getTransactionID() {
    return $this->trxnID;
  }

  /**
   * Get the subscription ID
   *
   * @return string
   */
  private function getSubscriptionID() {
    return $this->subscriptionID;
  }

  /**
   * CRM_Core_Payment_IPN constructor.
   *
   * @param ?\CRM_Core_Payment_AuthorizeNetCommon $paymentObject
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function __construct(?CRM_Core_Payment_AuthorizeNetCommon $paymentObject) {
    if ($paymentObject !== NULL && !is_subclass_of($paymentObject, 'CRM_Core_Payment_AuthorizeNetCommon')) {
      // This would be a coding error.
      throw new PaymentProcessorException(__CLASS__ . " constructor requires subclass of CRM_Core_Payment_AuthorizeNetCommon object");
    }
    $this->_paymentProcessor = $paymentObject;
    $this->logger = new \Civi\MJW\Logger(E::SHORT_NAME, $paymentObject->getID());
  }

  /**
   * When CiviCRM receives a webhook call this method (via handlePaymentNotification()).
   * This checks the webhook and either queues or triggers processing (depending on existing webhooks in queue)
   *
   * @return bool
   */
  public function onReceiveWebhook(): bool {
    // We could filter here which webhooks to handle instead of "all".

    // Decode and validate webhook
    // We don't need to pass in headers because the AuthnetWebhook class does that for us
    $webhook = new AuthnetWebhook(
      CRM_Core_Payment_AuthorizeNetCommon::getSignature(
        $this->_paymentProcessor->getPaymentProcessor()
      ),
      $this->getData());
    if (!$webhook->isValid()) {
      throw new PaymentProcessorException('Webhook not valid');
    }

    $this->setEventType($webhook->eventType);
    $this->setEventID($webhook->notificationId);

    // Get all received webhooks with matching event_id which have not been processed.
    // There should only be one.
    $paymentProcessorWebhooks = PaymentprocessorWebhook::get(FALSE)
      ->addWhere('payment_processor_id', '=', $this->_paymentProcessor->getID())
      ->addWhere('event_id', '=', $this->getEventID())
      //->addWhere('processed_date', 'IS NULL')
      ->execute()
      ->first();

    if (!empty($paymentProcessorWebhooks)) {
      $this->logger->logInfo("Duplicate webhook ignored: {$this->getEventID()}.{$this->getEventType()}");
      return TRUE;
    }

    $newWebhookEvent = PaymentprocessorWebhook::create(FALSE)
      ->addValue('payment_processor_id', $this->_paymentProcessor->getID())
      ->addValue('trigger', $this->getEventType())
      ->addValue('identifier', '')
      ->addValue('event_id', $this->getEventID())
      ->addValue('data', $this->getData())
      ->execute()
      ->first();

    return $this->processQueuedWebhookEvent($newWebhookEvent);
  }

  /**
   * Process a single queued event and update it.
   *
   * @param array $webhookEvent
   *
   * @return bool TRUE on success.
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function processQueuedWebhookEvent(array $webhookEvent) :bool {
    $processingResult = $this->processWebhookEvent($webhookEvent);
    // Update the stored webhook event.
    PaymentprocessorWebhook::update(FALSE)
      ->addWhere('id', '=', $webhookEvent['id'])
      ->addValue('status', $processingResult->ok ? 'success' : 'error')
      ->addValue('message', preg_replace('/^(.{250}).*/su', '$1 ...', $processingResult->message))
      ->addValue('processed_date', 'now')
      ->execute();

    return $processingResult->ok;
  }

  /**
   * Process the given webhook
   *
   * @param array $webhookEvent
   *
   * @return \StdClass
   *
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function processWebhookEvent(array $webhookEvent) :StdClass {
    $return = (object) ['message' => '', 'ok' => NULL, 'exception' => NULL];
    try {
      $webhookData = json_decode($webhookEvent['data']);
      switch ($webhookData->payload->entityName) {
        case 'transaction':
          $this->trxnID = $webhookData->payload->id;
          break;

        case 'subscription':
          $this->subscriptionID = $webhookData->payload->id;
          break;
      }

      // Here you can get more information about the transaction
      $request = AuthnetApiFactory::getJsonApiHandler(
        CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($this->_paymentProcessor->getPaymentProcessor()),
        CRM_Core_Payment_AuthorizeNetCommon::getTransactionKey($this->_paymentProcessor->getPaymentProcessor()),
        $this->_paymentProcessor->getIsTestMode() ? AuthnetApiFactory::USE_DEVELOPMENT_SERVER : AuthnetApiFactory::USE_PRODUCTION_SERVER
      );

      if ($this->getTransactionID()) {
        /** @var \Authnetjson\AuthnetJsonResponse $response */
        $response = $request->getTransactionDetailsRequest(['transId' => $this->getTransactionID()]);

        if ($response->messages->resultCode !== 'Ok') {
          $return->ok = FALSE;
          $return->message = 'Bad response from getTransactionDetailsRequest in IPN handler. ' . $response->getErrorText();
        }
        // CiviCRM implements separate payment processors for creditCard / eCheck but for AuthorizeNet there is only one.
        // So both CiviCRM payment processors receive the IPN notifications for each type.
        elseif (property_exists($response->transaction->payment, 'creditCard')
          && !($this->_paymentProcessor instanceof CRM_Core_Payment_AuthNetCreditcard)
          && ($this->_paymentProcessor instanceof CRM_Core_Payment_AuthNetEcheck)
        ) {
          $return->ok = TRUE;
          $return->message = 'Ignoring: Not processing creditCard payment with bankAccount processor';
        }
        elseif (property_exists($response->transaction->payment, 'bankAccount')
          && !($this->_paymentProcessor instanceof CRM_Core_Payment_AuthNetEcheck)
          && ($this->_paymentProcessor instanceof CRM_Core_Payment_AuthNetCreditcard)
        ) {
          $return->ok = TRUE;
          $return->message = 'Ignoring: Not processing bankAccount payment with CreditCard processor';
        }
        elseif (!property_exists($response->transaction->payment, 'creditCard') && !property_exists($response->transaction->payment, 'bankAccount')) {
          $return->ok = FALSE;
          $return->message = 'Unsupported payment type (not creditCard or bankAccount)';
        }

        // Set parameters required for IPN functions
        if (($return->ok === NULL) && $this->getParamFromResponse($response, 'is_recur')) {
          $subscriptionID = $this->getParamFromResponse($response, 'subscription_id');
          if (!$this->getRecurringContributionIDFromSubscriptionID($subscriptionID)) {
            $return->ok = FALSE;
            $return->message = $this->_paymentProcessor->getPaymentTypeLabel() . ": Could not find matching recurring contribution for subscription ID: {$subscriptionID}.";
          }
        }

        if ($return->ok === NULL) {
          // Process the webhook event
          $return = $this->processEventType($response);
        }
      }
      elseif ($this->getSubscriptionID()) {
        // We only need the contribution_recur_id
        $this->getRecurringContributionIDFromSubscriptionID($this->getSubscriptionID());
      }
      else {
        $return->ok = FALSE;
        $return->message = "No transactionId or subscriptionId found in payload";
      }
    }
    catch (Exception $e) {
      $return->ok = FALSE;
      $return->message = $e->getMessage() . "\n" . $e->getTraceAsString();
      $return->exception = $e;
    }
    $this->setEventID('');
    return $return;
  }

  /**
   * Process the received event in CiviCRM
   *
   * @param \Authnetjson\AuthnetJsonResponse $response
   *
   * @return \stdClass
   * @throws \CRM_Core_Exception
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function processEventType(\Authnetjson\AuthnetJsonResponse $response): stdClass {
    $return = (object) ['message' => '', 'ok' => FALSE, 'exception' => NULL];

    $eventDate = $this->getParamFromResponse($response, 'trxn_date');

    // Process the event
    switch ($this->getEventType()) {
      case 'net.authorize.payment.authcapture.created':
        // Notifies you that an authorization and capture transaction was created.
        // invoice_id is the same for all completed payments in a authnet subscription (recurring contribution)
        // transaction_id is unique for each completed payment in a authnet subscription (recurring contribution)
        // subscription_id is set on the recurring contribution and is unique per authnet subscription.

        // Check if we already recorded this payment?
        $payments = civicrm_api3('Mjwpayment', 'get_payment', [
          'trxn_id' => $this->getParamFromResponse($response, 'transaction_id'),
          'status_id' => 'Completed'
        ]);
        if ($payments['count'] > 0) {
          $contributionID = reset($payments['values'])['contribution_id'];
          // Payment already recorded
          $return->ok = TRUE;
          $return->message = 'Payment already completed. coid=' . $contributionID;
          return $return;
        }

        $invoiceId = $this->getParamFromResponse($response, 'invoice_id');
        // The retail mobile apps don't have an order/invoice ID when generating this event.
        if ($invoiceId) {
          $contribution = $this->getContributionFromTrxnInfo($invoiceId);
        }
        $pendingStatusId = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
        if (isset($contribution) && ($contribution['contribution_status_id'] == $pendingStatusId)) {
          $params = [
            'contribution_id' => $contribution['id'],
            'trxn_id' => $this->getParamFromResponse($response, 'transaction_id'),
            'order_reference' => $this->getParamFromResponse($response, 'transaction_id'),
            'trxn_date' => $eventDate,
            'total_amount' => $this->getParamFromResponse($response, 'total_amount'),
            'contribution_status_id' => $contribution['contribution_status_id']
          ];
          $this->updateContributionCompleted($params);
          $return->ok = TRUE;
          $return->message = 'Updated contributionID: ' . $contribution['id'] . ' to Completed';
        }
        elseif ($this->getParamFromResponse($response, 'is_recur')) {
          $params = [
            'trxn_id' => $this->getParamFromResponse($response, 'transaction_id'),
            'order_reference' => $this->getParamFromResponse($response, 'transaction_id'),
            'receive_date' => $eventDate,
            'contribution_recur_id' => $this->contribution_recur_id,
            'contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed'),
            'total_amount' => $this->getParamFromResponse($response, 'total_amount'),
          ];
          try {
            $newContributionID = $this->repeatContribution($params);
            $return->ok = TRUE;
            $return->message = 'Added next contribution (ID: ' . $newContributionID . ') (Completed) to recurID: ' . $this->contribution_recur_id;
          }
          catch (Exception $e) {
            // repeatContribution did not used to throw exception so we catch and return here.
            // We may want to change this in the future.
            $errorMessage = 'IPN: ' . $e->getMessage();
            $this->logger->logError($errorMessage);
            $return->ok = FALSE;
            $return->message = $errorMessage;
            return $return;
          }
        }
        break;

      case 'net.authorize.payment.refund.created':
        // Notifies you that a successfully settled transaction was refunded.
        $contribution = $this->getContributionFromTrxnInfo($this->getParamFromResponse($response, 'invoice_id'));
        if (!$contribution) {
          $return->ok = FALSE;
          $return->message = 'No matching contribution';
        }
        else {
          $contribution = civicrm_api3('Mjwpayment', 'get_contribution', ['contribution_id' => $contribution['id']]);

          $refundParams = [
            'contribution_id' => $contribution['id'],
            'total_amount' => 0 - abs($this->getParamFromResponse($response, 'refund_amount')),
            'trxn_date' => $this->getParamFromResponse($response, 'trxn_date'),
            //'fee_amount' => 0 - abs($this->fee),
            'trxn_id' => $this->getParamFromResponse($response, 'transaction_id'),
            'order_reference' => $this->getParamFromResponse($response, 'invoice_id'),
          ];
          if (isset($this->contribution['payments'])) {
            $refundStatusID = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Refunded');
            foreach ($this->contribution['payments'] as $payment) {
              if (((int) $payment['status_id'] === $refundStatusID) && ((float) $payment['total_amount'] === $refundParams['total_amount'])) {
                // Already refunded
                $return->ok = TRUE;
                $return->message = 'Refund already recorded';
                return $return;
              }
            }
            // This triggers the financial transactions/items to be updated correctly.
            $refundParams['cancelled_payment_id'] = reset($contribution['payments'])['id'];
          }

          $this->updateContributionRefund($refundParams);
          $return->ok = TRUE;
          $return->message = 'Updated contributionID: ' . $contribution['id'] . ' to Refunded';
        }
        break;

      case 'net.authorize.payment.void.created':
        // Notifies you that an unsettled transaction was voided.
        $contribution = $this->getContributionFromTrxnInfo($this->getParamFromResponse($response, 'invoice_id'));
        if (!$contribution) {
          $return->ok = FALSE;
          $return->message = 'No matching contribution';
        }
        else {
          $params = [
            'contribution_id' => $contribution['id'],
            'order_reference' => $this->getParamFromResponse($response, 'invoice_id'),
            'cancel_date' => $eventDate,
            'cancel_reason' => 'Transaction voided',
          ];
          $this->updateContributionFailed($params);
          $return->ok = TRUE;
          $return->message = 'Updated contributionID: ' . $contribution['id'] . ' to Failed';
        }
        break;

      case 'net.authorize.payment.fraud.held':
        // Notifies you that a transaction was held as suspicious.
        $return->ok = TRUE;
        $return->message = 'Ignoring: Not implemented';
        break;

      case 'net.authorize.payment.fraud.approved':
        // Notifies you that a previously held transaction was approved.
        $contribution = $this->getContributionFromTrxnInfo($this->getParamFromResponse($response, 'invoice_id'));
        if (!$contribution) {
          $return->ok = FALSE;
          $return->message = 'No matching contribution';
        }
        else {
          $params = [
            'contribution_id' => $contribution['id'],
            'trxn_id' => $this->getParamFromResponse($response, 'transaction_id'),
            'order_reference' => $this->getParamFromResponse($response, 'invoice_id'),
            'trxn_date' => $eventDate,
            'contribution_status_id' => $contribution['contribution_status_id'],
            'total_amount' => $this->getParamFromResponse($response, 'total_amount'),
          ];
          $this->updateContributionCompleted($params);
          $return->ok = TRUE;
          $return->message = 'Updated contributionID: ' . $contribution['id'] . ' to Completed';
        }
        break;

      case 'net.authorize.payment.fraud.declined':
        // Notifies you that a previously held transaction was declined.
        $contribution = $this->getContributionFromTrxnInfo($this->getParamFromResponse($response, 'invoice_id'));
        if (!$contribution) {
          $return->ok = FALSE;
          $return->message = 'No matching contribution';
        }
        else {
          $params = [
            'contribution_id' => $contribution['id'],
            'order_reference' => $this->getParamFromResponse($response, 'invoice_id'),
            'cancel_date' => $eventDate,
            'cancel_reason' => 'Fraud declined',
          ];
          $this->updateContributionFailed($params);
          $return->ok = TRUE;
          $return->message = 'Updated contributionID: ' . $contribution['id'] . ' to Failed';
        }
        break;

        // Now the "subscription" (recurring) ones
      // case 'net.authorize.customer.subscription.created':
        // Notifies you that a subscription was created.
      // case 'net.authorize.customer.subscription.updated':
        // Notifies you that a subscription was updated.
      // case 'net.authorize.customer.subscription.suspended':
        // Notifies you that a subscription was suspended.
      case 'net.authorize.customer.subscription.terminated':
        // Notifies you that a subscription was terminated.
      case 'net.authorize.customer.subscription.cancelled':
        // Notifies you that a subscription was cancelled.
        $this->updateRecurCancelled(['id' => $this->contribution_recur_id]);
        $return->ok = TRUE;
        $return->message = 'Updated recurID: ' . $this->contribution_recur_id . ' to Cancelled';
        break;

      // case 'net.authorize.customer.subscription.expiring':
        // Notifies you when a subscription has only one recurrence left to be charged.
    }

    return $return;
  }

  /**
   * Retrieve parameters from IPN response
   *
   * @param \Authnetjson\AuthnetJsonResponse $response
   * @param string $param
   *
   * @return mixed
   */
  protected function getParamFromResponse(\Authnetjson\AuthnetJsonResponse $response, string $param) {
    switch ($param) {
      case 'transaction_id':
        return $response->transaction->transId;

      case 'invoice_id':
        return $response->transaction->order->invoiceNumber;

      case 'refund_amount':
      case 'total_amount':
        return $response->transaction->authAmount;

      case 'is_recur':
        return $response->transaction->recurringBilling;

      case 'subscription_id':
        return $response->transaction->subscription->id;

      case 'subscription_payment_number':
        return $response->transaction->subscription->payNum;

      case 'trxn_date':
        // @todo check if we should use submitTimeLocal or submitTimeUTC here?
        //   See: https://lab.civicrm.org/extensions/authnet/-/issues/28
        //   We were using submitTimeLocal but changed to submitTimeUTC to see if time offset problem is fixed.
        return date('YmdHis', strtotime($response->transaction->submitTimeUTC));

    }
  }

  /**
   * Get the contribution ID from the transaction info.
   *
   * @param string $invoiceID
   *
   * @return array|NULL
   * @throws \CRM_Core_Exception
   */
  protected function getContributionFromTrxnInfo(string $invoiceID) {
    // invoiceNumber (refID) should be set on trxn_id of matching contribution
    $contributionParams = [
      'trxn_id' => $this->trxnID,
      'options' => ['limit' => 1, 'sort' => "id DESC"],
      'contribution_test' => $this->_paymentProcessor->getIsTestMode(),
    ];
    $contribution = civicrm_api3('Contribution', 'get', $contributionParams);

    // But if it is not we derived from first 20 chars of invoice_id so check there
    if (empty($contribution['id'])) {
      $contributionParams['trxn_id'] = ['LIKE' => "{$invoiceID}%"];
    }
    $contribution = civicrm_api3('Contribution', 'get', $contributionParams);
    // Or finally try via invoice_id directly (first 20 chars)
    if (empty($contribution['id'])) {
      unset($contributionParams['trxn_id']);
      $contributionParams['invoice_id'] = ['LIKE' => "{$invoiceID}%"];
    }
    $contribution = civicrm_api3('Contribution', 'get', $contributionParams);

    // We can't do anything without a matching contribution!
    if (empty($contribution['id'])) {
      return NULL;
    }

    return reset($contribution['values']);
  }

  /**
   * Map the subscription/invoiceID to the CiviCRM recurring contribution
   *
   * @param string $subscriptionID
   *
   * @return FALSE|int
   * @throws \CRM_Core_Exception
   */
  protected function getRecurringContributionIDFromSubscriptionID(string $subscriptionID) {
    $contributionRecur = ContributionRecur::get(FALSE)
      ->addWhere('is_test', 'IN', [FALSE, TRUE])
      ->addWhere('processor_id', '=', $subscriptionID)
      ->execute()
      ->first();
    if (empty($contributionRecur)) {
      return FALSE;
    }
    $this->contribution_recur_id = $contributionRecur['id'];
    return $this->contribution_recur_id;
  }

}
