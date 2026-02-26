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
use Civi\Payment\Exception\PaymentProcessorException;
use Civi\Payment\PropertyBag;
use CRM_AuthNetEcheck_ExtensionUtil as E;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use net\authorize\api\constants\ANetEnvironment as AnetEnvironment;
use Authnetjson\AuthnetJsonResponse as AuthnetJsonResponse;
use \Civi\MJW\Logger;

/*
 * @fixme: Deprecated function: Creation of dynamic property
 *   CRM_Core_Payment_AuthNetCreditcard::$logger is deprecated
 *
 * Added flag to allow dynamic properties to clear the debug warnings
 * as this only deals with log, but a better solution should be created
 * or this note should be removed if this is the correct solution.
 */
#[AllowDynamicProperties]

abstract class CRM_Core_Payment_AuthorizeNetCommon extends CRM_Core_Payment {

  use CRM_Core_Payment_MJWTrait;

  /**
   * @var \Civi\MJW\Logger $log
   */
  protected Logger $log;

  const AUTHNETECHECK_SKIP_WEBHOOK_CHECKS = 'AUTHNETECHECK_SKIP_WEBHOOK_CHECKS';

  /**
   * @fixme: Confirm that this is the correct "timezone" - we copied this from the original core Authorize.net processor.
   * @var string
   */
  const TIMEZONE = 'America/Denver';

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   */
  public function __construct($mode, &$paymentProcessor) {
    $this->_paymentProcessor = $paymentProcessor;
    $this->logger = new Logger(E::SHORT_NAME, $this->getID());
  }

  /**
   * @param array $paymentProcessor
   *
   * @return string
   */
  public static function getApiLoginId($paymentProcessor) {
    return trim($paymentProcessor['user_name'] ?? '');
  }

  /**
   * @param array $paymentProcessor
   *
   * @return string
   */
  public static function getTransactionKey($paymentProcessor) {
    return trim($paymentProcessor['password'] ?? '');
  }

  /**
   * @param array $paymentProcessor
   *
   * @return string
   */
  public static function getSignature($paymentProcessor) {
    return trim($paymentProcessor['signature'] ?? '');
  }

  /**
   * This function checks to see if we have the right config values.
   *
   * @return string
   *   the error message if any
   */
  public function checkConfig() {
    $error = [];
    if (!empty($error)) {
      return implode('<p>', $error);
    }
    else {
      return NULL;
    }
  }

  /**
   * Should the first payment date be configurable when setting up back office recurring payments.
   * In the case of Authorize.net this is an option
   * @return bool
   */
  protected function supportsFutureRecurStartDate() {
    return TRUE;
  }

  /**
   * Can recurring contributions be set against pledges.
   *
   * In practice all processors that use the baseIPN function to finish transactions or
   * call the completetransaction api support this by looking up previous contributions in the
   * series and, if there is a prior contribution against a pledge, and the pledge is not complete,
   * adding the new payment to the pledge.
   *
   * However, only enabling for processors it has been tested against.
   *
   * @return bool
   */
  protected function supportsRecurContributionsForPledges() {
    return TRUE;
  }

  /**
   * We can use the processor on the backend
   * @return bool
   */
  public function supportsBackOffice() {
    return TRUE;
  }

  public function supportsRecurring() {
    return TRUE;
  }

  /**
   * We can edit recurring contributions
   * @return bool
   */
  public function supportsEditRecurringContribution() {
    return TRUE;
  }

  public function supportsRefund() {
    return TRUE;
  }

  protected function getEnvironment() {
    return $this->getIsTestMode() ? AnetEnvironment::SANDBOX : AnetEnvironment::PRODUCTION;
  }

  /**
   * Submit a payment using Advanced Integration Method.
   *
   * Sets appropriate parameters and calls Smart Debit API to create a payment
   *
   * Payment processors should set payment_status_id.
   *
   * @param array|\Civi\Payment\PropertyBag $params
   *   Assoc array of input parameters for this transaction.
   *
   * @param string $component
   *
   * @return array
   *   Result array
   *
   * @throws \CRM_Core_Exception
   */
  public function doPayment(&$params, $component = 'contribute') {
    /* @var \Civi\Payment\PropertyBag $propertyBag */
    $propertyBag = $this->beginDoPayment($params);

    $zeroAmountPayment = $this->processZeroAmountPayment($propertyBag);
    if ($zeroAmountPayment) {
      return $zeroAmountPayment;
    }

    // @todo From here on we are using the array instead of propertyBag. To be converted later...
    $params = $this->getPropertyBagAsArray($propertyBag);

    if ($propertyBag->getIsRecur() && $propertyBag->has('contributionRecurID')) {
      return $this->doRecurPayment($params, $propertyBag);
    }

    $merchantAuthentication = $this->getMerchantAuthentication();
    $order = $this->getOrder($propertyBag);

    $customerData = $this->getCustomerDataType($params);
    $customerAddress = $this->getCustomerAddress($propertyBag);
    if (!empty($params['country']) && empty($customerAddress->getCountry())) {
      $customerAddress->setCountry($params['country']);
    }

    //create a bank debit transaction
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType("authCaptureTransaction");
    $transactionRequestType->setAmount($this->getAmount($params));
    $transactionRequestType->setCurrencyCode($this->getCurrency($params));
    $transactionRequestType->setPayment($this->getPaymentDetails($propertyBag));
    $transactionRequestType->setOrder($order);
    $transactionRequestType->setBillTo($customerAddress);
    $transactionRequestType->setCustomer($customerData);
    $transactionRequestType->setCustomerIP(CRM_Utils_System::ipAddress());
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($this->getInvoiceNumber($propertyBag));
    $request->setTransactionRequest($transactionRequestType);
    $controller = new AnetController\CreateTransactionController($request);
    /** @var \net\authorize\api\contract\v1\CreateTransactionResponse $response */
    $response = $controller->executeWithApiResponse($this->getEnvironment());

    if (!$response || !$response->getMessages()) {
      $this->handleError('', 'No response returned', $this->getErrorUrl($propertyBag));
    }

    $tresponse = $response->getTransactionResponse();

    if ($response->getMessages()->getResultCode() !== "Ok") {
      // resultCode !== 'Ok'
      $errorCode = '';
      $errorMessage = '';
      $errorMessages = [];
      if ($tresponse && $tresponse->getErrors()) {
        foreach ($tresponse->getErrors() as $tError) {
          $errorCode = $tError->getErrorCode();
          switch ($errorCode) {
            case '39':
              $errorMessages[] = $errorCode . ': ' . $tError->getErrorText() . ' (' . $this->getCurrency($params) . ')';
              break;

            default:
              $errorMessages[] = $errorCode . ': ' . $tError->getErrorText();
          }
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      elseif ($response->getMessages()) {
        foreach ($response->getMessages()->getMessage() as $rError) {
          $errorMessages[] = $rError->getCode() . ': ' . $rError->getText();
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      $this->handleError($errorCode, $errorMessage, $this->getErrorUrl($propertyBag));
    }
    else {
      // Result code is "Ok"
      if (!$tresponse) {
        $this->handleError('', 'No transaction response returned', $this->getErrorUrl($propertyBag));
      }
      $this->setPaymentProcessorTrxnID($tresponse->getTransId());
      $this->setPaymentProcessorOrderID($tresponse->getTransId());
      $returnParams = [];
      $returnParams = $this->setStatusPaymentPending($returnParams);

      switch ($tresponse->getResponseCode()) {

        case AuthnetJsonResponse::STATUS_APPROVED:
          $returnParams = $this->setStatusPaymentCompleted($returnParams);
          break;

        case AuthnetJsonResponse::STATUS_DECLINED:
        case AuthnetJsonResponse::STATUS_ERROR:
          if ($tresponse->getErrors()) {
            $this->handleError($tresponse->getErrors()[0]->getErrorCode(), $tresponse->getErrors()[0]->getErrorText(), $this->getErrorUrl($propertyBag));
          }
          else {
            $this->handleError('', 'Transaction Failed', $this->getErrorUrl($propertyBag));
          }
          break;

        case AuthnetJsonResponse::STATUS_HELD:
          // Keep it in pending state
          break;

      }
    }

    return $this->endDoPayment($returnParams);
  }

  /**
   * Submit a refund payment
   *
   * @param array $params
   *   Assoc array of input parameters for this transaction.
   *
   * @return array
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doRefund(&$params): array {
    $propertyBag = $this->beginDoRefund($params);
    if (!$propertyBag->has('transactionID') || !$propertyBag->has('amount')) {
      $errorMessage = $this->getLogPrefix() . 'doRefund: Missing mandatory parameters: transactionID, amount';
      $this->logger->logError($errorMessage);
      throw new PaymentProcessorException($errorMessage);
    }

    // https://developer.authorize.net/api/reference/index.html#payment-transactions-refund-a-transaction
    /* Create a merchantAuthenticationType object with authentication details
           retrieved from the constants file */
    $merchantAuthentication = $this->getMerchantAuthentication();

    // Set the transaction's refId
    $refId = 'ref' . time();

    //create a transaction
    $transactionRequest = new AnetAPI\TransactionRequestType();
    $transactionRequest->setTransactionType( "refundTransaction");
    $transactionRequest->setAmount($propertyBag->getAmount());

    $paymentDetails = $this->getRefundPaymentDetails($propertyBag);
    if ($paymentDetails) {
      $transactionRequest->setPayment($paymentDetails);
    }
    $transactionRequest->setRefTransId($propertyBag->getTransactionID());

    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($refId);
    $request->setTransactionRequest($transactionRequest);
    $controller = new AnetController\CreateTransactionController($request);
    $response = $controller->executeWithApiResponse($this->getEnvironment());

    if (!$response || !$response->getMessages()) {
      $this->handleError('', 'No response returned', $this->getErrorUrl($propertyBag));
    }

    $tresponse = $response->getTransactionResponse();

    if ($response->getMessages()->getResultCode() !== "Ok") {
      // resultCode !== 'Ok'
      $errorCode = '';
      $errorMessage = '';
      $errorMessages = [];
      if ($tresponse && $tresponse->getErrors()) {
        foreach ($tresponse->getErrors() as $tError) {
          $errorCode = $tError->getErrorCode();
          switch ($errorCode) {
            case '39':
              $errorMessages[] = $errorCode . ': ' . $tError->getErrorText() . ' (' . $this->getCurrency($params) . ')';
              break;

            default:
              $errorMessages[] = $errorCode . ': ' . $tError->getErrorText();
          }
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      elseif ($response->getMessages()) {
        foreach ($response->getMessages()->getMessage() as $rError) {
          $errorMessages[] = $rError->getCode() . ': ' . $rError->getText();
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      $this->handleError($errorCode, $errorMessage, $this->getErrorUrl($propertyBag));
    }
    else {
      // Result code is "Ok"
      if (!$tresponse) {
        $this->handleError('', 'No transaction response returned', $this->getErrorUrl($propertyBag));
      }

      switch ($tresponse->getResponseCode()) {
        // @fixme: Check these return codes (copied from doPayment)
        case AuthnetJsonResponse::STATUS_APPROVED:
          break;

        case AuthnetJsonResponse::STATUS_DECLINED:
        case AuthnetJsonResponse::STATUS_ERROR:
          if ($tresponse->getErrors()) {
            $this->handleError($tresponse->getErrors()[0]->getErrorCode(), $tresponse->getErrors()[0]->getErrorText(), $this->getErrorUrl($propertyBag));
          }
          else {
            $this->handleError('', 'Transaction Failed', $this->getErrorUrl($propertyBag));
          }
          break;

      }
    }

    $refundParams = [
      'refund_trxn_id' => $tresponse->getTransId(),
      'refund_status' => 'Completed',
      'fee_amount' => 0,
    ];
    return $refundParams;
  }

  /**
   * Get the merchant authentication for AuthNet
   *
   * @return \net\authorize\api\contract\v1\MerchantAuthenticationType
   */
  protected function getMerchantAuthentication() {
    // Create a merchantAuthenticationType object with authentication details
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName(self::getApiLoginId($this->_paymentProcessor));
    $merchantAuthentication->setTransactionKey(self::getTransactionKey($this->_paymentProcessor));
    return $merchantAuthentication;
  }

  /**
   * Get the customer address for AuthNet
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \net\authorize\api\contract\v1\CustomerAddressType
   */
  protected function getCustomerAddress($propertyBag) {
    // Set the customer's Bill To address
    $customerAddress = new AnetAPI\CustomerAddressType();
    $propertyBag->has('firstName') ? $customerAddress->setFirstName($propertyBag->getFirstName()) : NULL;
    $propertyBag->has('lastName') ? $customerAddress->setLastName($propertyBag->getLastName()) : NULL;

    $propertyBag->has('billingStreetAddress') ? $customerAddress->setAddress($propertyBag->getBillingStreetAddress()) : NULL;
    $propertyBag->has('billingCity') ? $customerAddress->setCity($propertyBag->getBillingCity()) : NULL;
    $propertyBag->has('billingStateProvince') ? $customerAddress->setState($propertyBag->getBillingStateProvince()) : NULL;
    $propertyBag->has('billingPostalCode') ? $customerAddress->setZip($propertyBag->getBillingPostalCode()) : NULL;
    $propertyBag->has('billingCountry') ? $customerAddress->setCountry($propertyBag->getBillingCountry()) : NULL;

    CRM_Core_Error::debug_var('billingaddress', $customerAddress);
    return $customerAddress;
  }

  /**
   * Get the customer data for AuthNet
   *
   * @param array $params
   *
   * @return \net\authorize\api\contract\v1\CustomerDataType
   */
  protected function getCustomerDataType($params) {
    // Set the customer's identifying information
    $customerData = new AnetAPI\CustomerDataType();
    $customerData->setType('individual');
    $customerData->setId($params['contactID']);
    $customerData->setEmail($this->getBillingEmail($params, $params['contactID']));
    return $customerData;
  }

  /**
   * Get the customer data for AuthNet
   *
   * @param array $params
   *
   * @return \net\authorize\api\contract\v1\CustomerType
   */
  protected function getCustomerType($params) {
    // Set the customer's identifying information
    $customerData = new AnetAPI\CustomerType();
    $customerData->setType('individual');
    $customerData->setId($params['contactID']);
    $customerData->setEmail($this->getBillingEmail($params, $params['contactID']));
    return $customerData;
  }

  /**
   * Get the order for AuthNet
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \net\authorize\api\contract\v1\OrderType
   */
  protected function getOrder(PropertyBag $propertyBag): AnetAPI\OrderType {
    // Order info
    $order = new AnetAPI\OrderType();
    $order->setInvoiceNumber($this->getInvoiceNumber($propertyBag));
    $order->setDescription($propertyBag->getter('description', TRUE), '');
    return $order;
  }

  /**
   * Get the recurring payment interval for AuthNet
   *
   * @param \Civi\Payment\PropertyBag $params
   *
   * @return \net\authorize\api\contract\v1\PaymentScheduleType\IntervalAType
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  protected function getRecurInterval($params) {
    $intervalLength = $params->getRecurFrequencyInterval();
    $intervalUnit = $params->getRecurFrequencyUnit();
    if ($intervalUnit == 'week') {
      $intervalLength *= 7;
      $intervalUnit = 'days';
    }
    elseif ($intervalUnit == 'year') {
      $intervalLength *= 12;
      $intervalUnit = 'months';
    }
    elseif ($intervalUnit == 'day') {
      $intervalUnit = 'days';
    }
    elseif ($intervalUnit == 'month') {
      $intervalUnit = 'months';
    }

    // interval cannot be less than 7 days or more than 1 year
    if ($intervalUnit == 'days') {
      if ($intervalLength < 7) {
        $this->handleError('', 'Payment interval must be at least one week', $params->getCustomProperty('error_url'));
      }
      elseif ($intervalLength > 365) {
        $this->handleError('', 'Payment interval may not be longer than one year', $params->getCustomProperty('error_url'));
      }
    }
    elseif ($intervalUnit == 'months') {
      if ($intervalLength < 1) {
        $this->handleError('', 'Payment interval must be at least one week', $params->getCustomProperty('error_url'));
      }
      elseif ($intervalLength > 12) {
        $this->handleError('', 'Payment interval may not be longer than one year', $params->getCustomProperty('error_url'));
      }
    }

    $interval = new AnetAPI\PaymentScheduleType\IntervalAType();
    $interval->setLength($intervalLength);
    $interval->setUnit($intervalUnit);
    return $interval;
  }

  /**
   * Get the payment schedule for AuthNet
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   * @param \net\authorize\api\contract\v1\PaymentScheduleType\IntervalAType|NULL $interval
   *
   * @return \net\authorize\api\contract\v1\PaymentScheduleType
   */
  protected function getRecurSchedule(PropertyBag $propertyBag, AnetAPI\PaymentScheduleType\IntervalAType $interval = NULL): AnetAPI\PaymentScheduleType {
    $paymentSchedule = new AnetAPI\PaymentScheduleType();
    if ($interval) {
      $paymentSchedule->setInterval($interval);
    }

    // for open ended subscription totalOccurrences has to be 9999
    $installments = $propertyBag->has('recurInstallments') && $propertyBag->getRecurInstallments() ? $propertyBag->getRecurInstallments() : 9999;
    $paymentSchedule->setTotalOccurrences($installments);
    return $paymentSchedule;
  }

  /**
   * @param string $startDateString
   *
   * @return \DateTime
   */
  protected function getRecurStartDate(string $startDateString = 'now'): DateTime {
    //allow for post dated payment if set in form
    $startDate = date_create($startDateString);

    /* Format start date in Mountain Time to avoid Authorize.net error E00017
     * we do this only if the day we are setting our start time to is LESS than the current
     * day in mountaintime (ie. the server time of the A-net server). A.net won't accept a date
     * earlier than the current date on it's server so if we are in PST we might need to use mountain
     * time to bring our date forward. But if we are submitting something future dated we want
     * the date we entered to be respected
     */
    $minDate = date_create('now', new DateTimeZone(self::TIMEZONE));
    if (strtotime($startDate->format('Y-m-d')) < strtotime($minDate->format('Y-m-d'))) {
      $startDate->setTimezone(new DateTimeZone(self::TIMEZONE));
    }
    return $startDate;
  }

  /**
   * Submit an Automated Recurring Billing subscription.
   *
   * @param array $params
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function doRecurPayment(array $params, PropertyBag $propertyBag): array {
    $merchantAuthentication = $this->getMerchantAuthentication();
    // Subscription Type Info
    $subscription = new AnetAPI\ARBSubscriptionType();
    $subscription->setName($this->getPaymentDescription($params));
    $interval = $this->getRecurInterval($propertyBag);
    $paymentSchedule = $this->getRecurSchedule($propertyBag, $interval);
    $paymentSchedule->setStartDate($this->getRecurStartDate($params['receive_date'] ?? 'now'));

    $subscription->setPaymentSchedule($paymentSchedule);
    $subscription->setAmount($propertyBag->getAmount());
    $subscription->setPayment($this->getPaymentDetails($propertyBag));

    $order = $this->getOrder($propertyBag);
    $subscription->setOrder($order);

    $customerAddress = $this->getCustomerAddress($propertyBag);
    $customerData = $this->getCustomerType($params);
    $subscription->setBillTo($customerAddress);
    $subscription->setCustomer($customerData);

    $request = new AnetAPI\ARBCreateSubscriptionRequest();
    $request->setmerchantAuthentication($merchantAuthentication);
    $request->setRefId($this->getInvoiceNumber($propertyBag));
    $request->setSubscription($subscription);
    $controller = new AnetController\ARBCreateSubscriptionController($request);
    /** @var \net\authorize\api\contract\v1\ARBCreateSubscriptionResponse $response */
    $response = $controller->executeWithApiResponse($this->getIsTestMode() ? AnetEnvironment::SANDBOX : AnetEnvironment::PRODUCTION);

    if (!$response || !$response->getMessages()) {
      $this->handleError('', 'No response returned', $this->getErrorUrl($propertyBag));
    }

    if ($response->getMessages()->getResultCode() == "Ok") {
      $this->setPaymentProcessorOrderID($response->getRefId());

      $recurParams = [
        'id' => $propertyBag->getContributionRecurID(),
        'processor_id' => $response->getSubscriptionId(),
        'auto_renew' => 1,
      ];
      if (!empty($params['installments'])) {
        if (empty($params['start_date'])) {
          $params['start_date'] = date('YmdHis');
        }
      }

      // Update the recurring payment
      ContributionRecur::update(FALSE)
        ->setValues($recurParams)
        ->addWhere('id', '=', $propertyBag->getContributionRecurID())
        ->execute();
      return $this->endDoPayment($params);
    }
    else {
      $errorCode = '';
      $errorMessage = '';
      $errorMessages = [];
      if ($response->getMessages()) {
        foreach ($response->getMessages()->getMessage() as $rError) {
          $errorMessages[] = $rError->getCode() . ': ' . $rError->getText();
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      $this->handleError($errorCode, $errorMessage, $this->getErrorUrl($propertyBag));
    }
  }

  /**
   * @param string $message
   * @param array $params
   *
   * @return bool
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function updateSubscriptionBillingInfo(&$message = '', $params = []) {
    $propertyBag = $this->beginUpdateSubscriptionBillingInfo($params);

    $merchantAuthentication = $this->getMerchantAuthentication();

    $subscription = new AnetAPI\ARBSubscriptionType();
    $subscription->setPayment($this->getPaymentDetails($propertyBag));

    $customerAddress = $this->getCustomerAddress($propertyBag);
    //$customerData = $this->getCustomerType();
    $subscription->setBillTo($customerAddress);
    //$subscription->setCustomer($customerData);

    $request = new AnetAPI\ARBUpdateSubscriptionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($this->getInvoiceNumber($propertyBag));
    $request->setSubscriptionId($propertyBag->getRecurProcessorID());
    $request->setSubscription($subscription);
    $controller = new AnetController\ARBUpdateSubscriptionController($request);
    /** @var \net\authorize\api\contract\v1\ARBUpdateSubscriptionResponse $response */
    $response = $controller->executeWithApiResponse($this->getIsTestMode() ? AnetEnvironment::SANDBOX : AnetEnvironment::PRODUCTION);

    if (!$response || !$response->getMessages()) {
      $this->handleError('', 'No response returned', $this->getErrorUrl($propertyBag));
    }

    if ($response->getMessages()->getResultCode() != "Ok") {
      $errorCode = '';
      $errorMessage = '';
      $errorMessages = [];
      if ($response->getMessages()) {
        foreach ($response->getMessages()->getMessage() as $rError) {
          $errorMessages[] = $rError->getCode() . ': ' . $rError->getText();
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      $this->handleError($errorCode, $errorMessage, $this->getErrorUrl($propertyBag));
    }

    return TRUE;
  }

  /**
   * Change the subscription amount
   *
   * @param string $message
   * @param array $params
   *
   * @return bool
   * @throws \Exception
   */
  public function changeSubscriptionAmount(&$message = '', $params = []) {
    $propertyBag = $this->beginChangeSubscriptionAmount($params);

    $merchantAuthentication = $this->getMerchantAuthentication();

    $subscription = new AnetAPI\ARBSubscriptionType();
    $paymentSchedule = $this->getRecurSchedule($propertyBag);
    $subscription->setPaymentSchedule($paymentSchedule);
    $subscription->setAmount($propertyBag->getAmount());
    $request = new AnetAPI\ARBUpdateSubscriptionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($this->getInvoiceNumber($propertyBag));
    $request->setSubscriptionId($propertyBag->getRecurProcessorID());
    $request->setSubscription($subscription);
    $controller = new AnetController\ARBUpdateSubscriptionController($request);
    /** @var \net\authorize\api\contract\v1\ARBUpdateSubscriptionResponse $response */
    $response = $controller->executeWithApiResponse($this->getIsTestMode() ? AnetEnvironment::SANDBOX : AnetEnvironment::PRODUCTION);

    if (!$response || !$response->getMessages()) {
      $this->handleError('', 'No response returned', $this->getErrorUrl($propertyBag));
    }

    if ($response->getMessages()->getResultCode() != "Ok") {
      $errorCode = '';
      $errorMessage = '';
      $errorMessages = [];
      if ($response->getMessages()) {
        foreach ($response->getMessages()->getMessage() as $rError) {
          $errorMessages[] = $rError->getCode() . ': ' . $rError->getText();
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      $this->handleError($errorCode, $errorMessage, $this->getErrorUrl($propertyBag));
    }

    return TRUE;
  }

  /**
   * Does this processor support cancelling recurring contributions through code.
   *
   * If the processor returns true it must be possible to take action from within CiviCRM
   * that will result in no further payments being processed.
   *
   * @return bool
   */
  protected function supportsCancelRecurring() {
    return TRUE;
  }

  /**
   * Does the processor support the user having a choice as to whether to cancel the recurring with the processor?
   *
   * If this returns TRUE then there will be an option to send a cancellation request in the cancellation form.
   *
   * @return bool
   */
  protected function supportsCancelRecurringNotifyOptional() {
    return TRUE;
  }

  /**
   * Attempt to cancel the subscription at AuthorizeNet.
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return array|null[]
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doCancelRecurring(PropertyBag $propertyBag) {
    // By default we always notify the processor and we don't give the user the option
    // because supportsCancelRecurringNotifyOptional() = FALSE
    if (!$propertyBag->has('isNotifyProcessorOnCancelRecur')) {
      // If isNotifyProcessorOnCancelRecur is NOT set then we set our default
      $propertyBag->setIsNotifyProcessorOnCancelRecur(TRUE);
    }
    $notifyProcessor = $propertyBag->getIsNotifyProcessorOnCancelRecur();

    if (!$notifyProcessor) {
      return ['message' => E::ts('Successfully cancelled the subscription in CiviCRM ONLY.')];
    }

    if (!$propertyBag->has('recurProcessorID')) {
      $errorMessage = E::ts('The recurring contribution cannot be cancelled (No reference (contribution_recur.processor_id) found).');
      $this->logger->logError($errorMessage);
      throw new PaymentProcessorException($errorMessage);
    }

    $merchantAuthentication = $this->getMerchantAuthentication();

    $request = new AnetAPI\ARBCancelSubscriptionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($this->getInvoiceNumber($propertyBag));
    $request->setSubscriptionId($propertyBag->getRecurProcessorID());
    $controller = new AnetController\ARBCancelSubscriptionController($request);
    /** @var \net\authorize\api\contract\v1\ARBCancelSubscriptionResponse $response */
    $response = $controller->executeWithApiResponse($this->getIsTestMode() ? AnetEnvironment::SANDBOX : AnetEnvironment::PRODUCTION);

    if (!$response || !$response->getMessages()) {
      $this->handleError('', 'No response returned');
    }

    if ($response->getMessages()->getResultCode() != "Ok") {
      $errorCode = '';
      $errorMessage = '';
      $errorMessages = [];
      if ($response->getMessages()) {
        foreach ($response->getMessages()->getMessage() as $rError) {
          $errorMessages[] = $rError->getCode() . ': ' . $rError->getText();
        }
        $errorMessage = implode(', ', $errorMessages);
      }
      $this->handleError($errorCode, $errorMessage);
    }

    return ['message' => E::ts('Successfully cancelled the subscription at Authorize.net.')];
  }

  /**
   * Return the invoice number formatted in the "standard" way
   * @fixme This is how it has always been done with authnet and is not necessarily the best way
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return string
   */
  protected function getInvoiceNumber(PropertyBag $propertyBag): string {
    if ($propertyBag->has('invoiceID')) {
      return substr($propertyBag->getInvoiceID(), 0, 20);
    }
    return '';
  }

  /**
   * Set the payment details for the subscription
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return AnetAPI\PaymentType
   */
  abstract protected function getPaymentDetails(PropertyBag $propertyBag);

  /**
   * Get the payment details for the payment
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \net\authorize\api\contract\v1\PaymentType|NULL
   */
  protected function getRefundPaymentDetails(PropertyBag $propertyBag) {
    $request = new AnetAPI\GetTransactionDetailsRequest();
    $request->setMerchantAuthentication($this->getMerchantAuthentication());
    $request->setTransId($propertyBag->getTransactionID());

    $controller = new AnetController\GetTransactionDetailsController($request);

    /**
     * @var \net\authorize\api\contract\v1\GetTransactionDetailsResponse $response
     */
    $response = $controller->executeWithApiResponse($this->getEnvironment());

    if (($response !== NULL) && ($response->getMessages()->getResultCode() === 'Ok')) {
      $payment = $response->getTransaction()->getPayment();
    }
    else {
      $errorMessage = $this->getLogPrefix() . 'doRefund: Could not get original payment details from AuthorizeNet';
      $this->logger->logError($errorMessage);
      throw new PaymentProcessorException($errorMessage);
    }

    if ($payment->getCreditCard()) {
      // Create the payment refund data for a credit card
      $creditCard = new AnetAPI\CreditCardType();
      $creditCard->setCardNumber($payment->getCreditCard()->getCardNumber());
      $creditCard->setExpirationDate("XXXX");
      $paymentOne = new AnetAPI\PaymentType();
      $paymentOne->setCreditCard($creditCard);
    }
    elseif ($payment->getBankAccount()) {
      // Create the payment refund data for a bank account
      // See: https://developer.authorize.net/api/reference/features/echeck.html
      $bankAccount = new AnetAPI\BankAccountType();
      // AccountType and RoutingNumber are already masked when we retrieve them
      $bankAccount->setAccountType($payment->getBankAccount()->getAccountType());
      // String, up to 9 characters. For refunds, use "XXXX" plus the first four digits of the account number.
      $bankAccount->setRoutingNumber($payment->getBankAccount()->getRoutingNumber());
      // String, up to 17 characters. For refunds, use "XXXX" plus the first four digits of the account number.
      $bankAccount->setAccountNumber($payment->getBankAccount()->getAccountNumber());
      $bankAccount->setNameOnAccount($payment->getBankAccount()->getNameOnAccount());
      // Looks like not required and can actually cause "This echeck.net type is not allowed" if provided.
      // $bankAccount->setEcheckType($payment->getBankAccount()->getEcheckType());
      $bankAccount->setBankName($payment->getBankAccount()->getBankName());
      $paymentOne = new AnetAPI\PaymentType();
      $paymentOne->setBankAccount($bankAccount);
    }
    else {
      $errorMessage = $this->getLogPrefix() . 'doRefund: Unknown payment type (not one of CreditCard or BankAccount)';
      $this->logger->logError($errorMessage);
      throw new PaymentProcessorException($errorMessage);
    }
    return $paymentOne;
  }

  /**
   * Process incoming payment notification (IPN).
   *
   * @throws \CRM_Core_Exception
   */
  public function handlePaymentNotification() {
    // Set default http response to 200
    http_response_code(200);
    $dataRaw = file_get_contents("php://input");
    $ipnClass = new CRM_Core_Payment_AuthNetIPN($this);
    $ipnClass->setData($dataRaw);
    if (!$ipnClass->onReceiveWebhook()) {
      http_response_code(400);
    }
  }

  /**
   * Called by mjwshared extension's queue processor api3 Job.process_paymentprocessor_webhooks
   *
   * The array parameter contains a row of PaymentprocessorWebhook data, which represents a single PaymentprocessWebhook event
   *
   * Return TRUE for success, FALSE if there's a problem
   */
  public function processWebhookEvent(array $webhookEvent) :bool {
    // If there is another copy of this event in the table with a lower ID, then
    // this is a duplicate that should be ignored. We do not worry if there is one with a higher ID
    // because that means that while there are duplicates, we'll only process the one with the lowest ID.
    $duplicates = PaymentprocessorWebhook::get(FALSE)
      ->selectRowCount()
      ->addWhere('event_id', '=', $webhookEvent['event_id'])
      ->addWhere('id', '<', $webhookEvent['id'])
      ->execute()->count();
    if ($duplicates) {
      PaymentprocessorWebhook::update(FALSE)
        ->addWhere('id', '=', $webhookEvent['id'])
        ->addValue('status', 'error')
        ->addValue('message', 'Refusing to process this event as it is a duplicate.')
        ->execute();
      return FALSE;
    }

    $handler = new CRM_Core_Payment_AuthNetIPN($this);
    $handler->setEventID($webhookEvent['event_id']);
    $handler->setEventType($webhookEvent['trigger']);
    return $handler->processQueuedWebhookEvent($webhookEvent);
  }

  /**
   * @return string
   */
  public function getLogPrefix(): string {
    return 'AuthNet(' . $this->getID() . '): ';
  }

}
