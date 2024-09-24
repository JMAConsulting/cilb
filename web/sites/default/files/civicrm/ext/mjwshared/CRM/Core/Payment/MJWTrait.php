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
use Civi\Api4\ContributionRecur;
use Civi\Api4\Phone;
use Civi\Payment\Exception\PaymentProcessorException;
use Civi\Payment\PropertyBag;
use CRM_Mjwshared_ExtensionUtil as E;
/**
 * Shared payment functions that should one day be migrated to CiviCRM core
 * Trait CRM_Core_Payment_MJWTrait
 */
trait CRM_Core_Payment_MJWTrait {

  /**
   * @var array params passed for payment
   */
  protected $_params = [];

  /**
   * @var string The unique charge/trxn reference from the payment processor
   */
  private $paymentProcessorTrxnID;

  /**
   * @var string The unique invoice/order reference from the payment processor
   */
  private $paymentProcessorOrderID;

  /**
   * @var string The unique subscription reference from the payment processor
   */
  private $paymentProcessorSubscriptionID;

  /**
   * @var bool Throw an exception in handleError.
   */
  public $handleErrorThrowsException = FALSE;

  /**
   * Get the billing email address
   *
   * @param \Civi\Payment\PropertyBag|array $propertyBag
   * @param int $contactID
   *
   * @return string
   */
  public function getBillingEmail($propertyBag, $contactID = NULL): string {
    // We want this function to take a single argument - propertyBag but for legacy compatibility
    //   we still accept an array and the second parameter contactID.
    $propertyBag = PropertyBag::cast($propertyBag);
    if (empty($contactID) && $propertyBag->has('contactID')) {
      $contactID = $propertyBag->getContactID();
    }
    if ($propertyBag->has('email')) {
      $emailAddress = $propertyBag->getEmail();
    }

    if (empty($emailAddress) && !empty($contactID)) {
      // Try and retrieve an email address from Contact ID
      $emailAddresses = \Civi\Api4\Email::get(FALSE)
        ->addWhere('contact_id', '=', $contactID)
        ->execute();

      $other_options = [];
      $billingLocationId = CRM_Core_BAO_LocationType::getBilling();
      foreach ($emailAddresses as $row) {
        if ($row['location_type_id'] == $billingLocationId) {
          return $row['email'];
        }
        elseif ($row['is_primary']) {
          array_unshift($other_options, $row['email']);
        }
        else {
          $other_options[] = $row['email'];
        }
      }
      if ($other_options) {
        $emailAddress = $other_options[0];
      }
    }
    return $emailAddress ?? '';
  }

  /**
   * Get the billing email address
   *
   * @deprecated This is not used anywhere and will be removed in a future version
   *
   * @param array $params
   * @param int $contactID
   *
   * @return string|NULL
   */
  protected function getBillingPhone($params, $contactID) {
    $billingLocationTypeID = CRM_Core_PseudoConstant::getKey('CRM_Core_DAO_Address', 'location_type_id', 'Billing');
    $phoneNumber = $params["phone-{$billingLocationTypeID}"] ?? $params['phone-Primary'] ?? $params['phone'] ?? NULL;

    if (empty($phoneNumber) && !empty($contactID)) {
      // Try and retrieve a phone number from Contact ID
      $phone = Phone::get(FALSE)
        ->addSelect('phone')
        ->addWhere('contact_id', '=', $contactID)
        ->addOrderBy('is_primary', 'DESC')
        ->execute()
        ->first();
    }
    return $phone['phone'] ?? '';
  }

  /**
   * Get the contact id
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return int|NULL The ContactID
   */
  protected function getContactId(&$propertyBag) {
    if ($propertyBag->has('contactID')) {
      return $propertyBag->getContactID();
    }

    // cms_contactID is set by: membership payment workflow when "on behalf of" / related contact is used.
    $contactId = $propertyBag->getter('cms_contactID', TRUE) ?? $propertyBag->getter('cid', TRUE);
    if (empty($contactId)) {
      // FIXME: Ref: https://lab.civicrm.org/extensions/stripe/issues/16
      // The problem is that when registering for a paid event, civicrm does not pass in the
      // contact id to the payment processor (civicrm version 5.3). So, I had to patch your
      // getContactId to check the session for a contact id. It's a hack and probably should be fixed in core.
      // The code below is exactly what CiviEvent does, but does not pass it through to the next function.
      $session = CRM_Core_Session::singleton();
      $contactId = $session->get('transaction.userID', NULL);
    }
    if (!empty($contactId)) {
      $propertyBag->setContactID($contactId);
      return $propertyBag->getContactID();
    }
    return NULL;
  }

  /**
   * Get the recurring contribution ID from parameters
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return int|null
   */
  protected function getRecurringContributionId(PropertyBag $propertyBag) {
    if ($propertyBag->has('contributionRecurID')) {
      return $propertyBag->getContributionRecurID();
    }

    if ($propertyBag->has('contributionID')) {
      return Contribution::get(FALSE)
        ->addSelect('contribution_recur_id')
        ->addWhere('id', '=', $propertyBag->getContributionID())
        ->addWhere('is_test', 'IN', [TRUE, FALSE])
        ->execute()
        ->first()['contribution_recur_id'] ?? NULL;
    }

    if ($propertyBag->has('processorID')) {
      $propertyBag->getRecurProcessorID();
      return ContributionRecur::get(FALSE)
        ->addSelect('id')
        ->addWhere('processor_id', '=', $propertyBag->getRecurProcessorID())
        ->addWhere('is_test', 'IN', [TRUE, FALSE])
        ->execute()
        ->first()['id'] ?? NULL;
    }
    return NULL;
  }

  /**
   * @param array $params
   *
   * @return mixed|null
   */
  protected function getFinancialTypeID($params) {
    return $params['financial_type_id'] ?? $params['financialTypeID'] ?? NULL;
  }

  /**
   * Get the currency configured for the form when it is loaded
   *
   * @param \CRM_Core_Form $form
   *
   * @return string
   */
  public function getDefaultCurrencyForForm($form): string {
    if (method_exists($form, 'getCurrency')) {
      $currency = $form->getCurrency();
    }

    if (empty($currency) || $currency === 'undefined') {
      // If we can't find it we'll use the default from the configuration
      $currency = Civi::settings()->get('defaultCurrency');
    }
    return $currency;
  }

  /**
   *
   * @param array $params ['name' => payment instrument name]
   *
   * @return int|null
   * @throws \CRM_Core_Exception
   */
  public static function createPaymentInstrument($params) {
    $mandatoryParams = ['name'];
    foreach ($mandatoryParams as $value) {
      if (empty($params[$value])) {
        Civi::log()->error('createPaymentInstrument: Missing mandatory parameter: ' . $value);
        return NULL;
      }
    }

    // Create a Payment Instrument
    // See if we already have this type
    $paymentInstrument = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => "payment_instrument",
      'name' => $params['name'],
    ]);
    if (empty($paymentInstrument['count'])) {
      // Otherwise create it
      try {
        $financialAccount = civicrm_api3('FinancialAccount', 'getsingle', [
          'financial_account_type_id' => "Asset",
          'name' => "Payment Processor Account",
        ]);
      }
      catch (Exception $e) {
        $financialAccount = civicrm_api3('FinancialAccount', 'getsingle', [
          'financial_account_type_id' => "Asset",
          'name' => "Payment Processor Account",
          'options' => ['limit' => 1, 'sort' => "id ASC"],
        ]);
      }

      $paymentParams = [
        'option_group_id' => "payment_instrument",
        'name' => $params['name'],
        'description' => $params['name'],
        'financial_account_id' => $financialAccount['id'],
      ];
      $paymentInstrument = civicrm_api3('OptionValue', 'create', $paymentParams);
      $paymentInstrumentId = $paymentInstrument['values'][$paymentInstrument['id']]['value'];
    }
    else {
      $paymentInstrumentId = $paymentInstrument['id'];
    }
    return $paymentInstrumentId;
  }

  /**
   * Get the error URL to "bounce" the user back to.
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return string|null
   */
  public function generateErrorUrl($propertyBag) {
    // Get proper entry URL for returning on error.
    if (!$propertyBag->has('qfKey') || !$propertyBag->has('entryURL')) {
      // Probably not called from a civicrm form (e.g. webform) -
      // will return error object to original api caller.
      $errorUrl = NULL;
    }
    else {
      $qfKey = $propertyBag->getCustomProperty('qfKey');
      $parsedUrl = parse_url($propertyBag->getCustomProperty('entryURL'));
      $urlPath = trim($parsedUrl['path'], '/');
      $query = $parsedUrl['query'];
      if (strpos($query, '_qf_Main_display=1') === FALSE) {
        $query .= '&_qf_Main_display=1';
      }
      if (strpos($query, 'qfKey=') === FALSE) {
        $query .= "&qfKey={$qfKey}";
      }
      $errorUrl = CRM_Utils_System::url($urlPath, $query, FALSE, NULL, FALSE);
    }
    return $errorUrl;
  }

  /**
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return mixed|void|null
   */
  public function getErrorUrl(\Civi\Payment\PropertyBag $propertyBag): string {
    if ($propertyBag->has('error_url')) {
      return $propertyBag->getCustomProperty('error_url');
    }
    return '';
  }


  /**
   * Are we using a test processor?
   *
   * @return bool
   */
  public function getIsTestMode() {
    return isset($this->_paymentProcessor['is_test']) && $this->_paymentProcessor['is_test'] ? 1 : 0;
  }

  /**
   * Handle an error and notify the user
   *
   * @param string $errorCode
   * @param string $errorMessage
   * @param string $bounceURL
   * @param bool $log
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   *   (or CRM_Core_Error::statusBounce if URL is specified)
   */
  private function handleError(string $errorCode = '', string $errorMessage = '', string $bounceURL = '', bool $log = TRUE) {
    $errorMessage = empty($errorMessage) ? 'Unknown System Error.' : $errorMessage;
    $message = $errorMessage . (!empty($errorCode) ? " - {$errorCode}" : '');

    if ($log) {
      Civi::log()->error($this->getPaymentTypeLabel() . ' Payment Error: ' . $message);
    }
    if ($this->handleErrorThrowsException) {
      // We're in a test environment. Throw exception.
      throw new \Exception('Exception thrown to avoid statusBounce because handleErrorThrowsException is set.' . $message);
    }

    if (!empty($bounceURL)) {
      CRM_Core_Error::statusBounce($message, $bounceURL, $this->getPaymentTypeLabel());
    }
    throw new PaymentProcessorException($errorMessage, $errorCode);
  }

  /**
   * Get the label for the payment processor
   *
   * @return string
   */
  public function getPaymentProcessorLabel() {
    return $this->_paymentProcessor['name'];
  }

  /**
   * Allow (phpunit test code) to configure handleError to throw an exception,
   * thereby avoiding a statusBounce which is hard to deal with in tests.
   */
  public function setHandleErrorThrowsException(bool $value) {
    $this->handleErrorThrowsException = $value;
  }
  /**
   * Set the payment processor Transaction ID
   *
   * @param string $trxnID
   */
  protected function setPaymentProcessorTrxnID($trxnID) {
    $this->paymentProcessorTrxnID = $trxnID;
  }

  /**
   * Get the payment processor Transaction ID
   *
   * @return string
   */
  protected function getPaymentProcessorTrxnID() {
    return $this->paymentProcessorTrxnID;
  }

  /**
   * Set the payment processor Order ID
   *
   * @param string $orderID
   */
  protected function setPaymentProcessorOrderID($orderID) {
    $this->paymentProcessorOrderID = $orderID;
  }

  /**
   * Get the payment processor Order ID
   *
   * @return string
   */
  protected function getPaymentProcessorOrderID() {
    return $this->paymentProcessorOrderID;
  }

  /**
   * Set the payment processor Subscription ID
   *
   * @param string $subscriptionID
   */
  protected function setPaymentProcessorSubscriptionID($subscriptionID) {
    $this->paymentProcessorSubscriptionID = $subscriptionID;
  }

  /**
   * Get the payment processor Subscription ID
   *
   * @return string
   */
  protected function getPaymentProcessorSubscriptionID() {
    return $this->paymentProcessorSubscriptionID;
  }

  /**
   * In some cases a payment is still submitted via the payment processor with zero amount.
   * See eg. https://lab.civicrm.org/extensions/stripe/-/issues/256.
   * When you have a 0 membership option and a confirmation page.
   * This function should be called in doPayment() before beginDoPayment()
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return array|false
   */
  protected function processZeroAmountPayment(PropertyBag $propertyBag) {
    // If we have a $0 amount, skip call to processor and set payment_status to Completed.
    // https://github.com/civicrm/civicrm-core/blob/master/CRM/Core/Payment.php#L1362
    if ($propertyBag->getAmount() == 0) {
      return $this->setStatusPaymentCompleted([]);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Call this at the beginning of call to CRM_Core_Payment::doPayment()
   * to ensure that all necessary parameters are set.
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \Civi\Payment\PropertyBag
   */
  protected function beginDoPayment($propertyBag) {
    // Make sure it's a propertyBag
    $propertyBag = PropertyBag::cast($propertyBag);
    // This currently doesn't have a default (5.27). Should be fixed in a future version of CiviCRM.
    if (!$propertyBag->has('isRecur')) {
      $propertyBag->setIsRecur(FALSE);
    }

    if (!$propertyBag->has('billingCountry') && $propertyBag->has('country')) {
      $propertyBag->setBillingCountry($propertyBag->getCustomProperty('country'));
    }

    // Make sure we have a description for the contribution
    if (!$propertyBag->has('description')) {
      $propertyBag->setDescription(E::ts('Contribution: %1', [1 => $this->getPaymentProcessorLabel()]));
    }

    $propertyBag->setCustomProperty('error_url', $this->getErrorUrl($propertyBag));

    // Make sure we have a contactID set on propertyBag
    $this->getContactId($propertyBag);
    return $propertyBag;
  }

  /**
   *  Call this at the beginning of call to CRM_Core_Payment::doRefund()
   *  to ensure that all necessary parameters are set.
   *
   * @param \Civi\Payment\PropertyBag|array $propertyBag
   *
   * @return \Civi\Payment\PropertyBag
   */
  protected function beginDoRefund($propertyBag) {
    // Make sure it's a propertyBag
    $propertyBag = PropertyBag::cast($propertyBag);

    // Make sure we have a contactID set on propertyBag
    $this->getContactId($propertyBag);

    // In 5.72 propertyBag maps transaction_id to transactionID but trxn_id is not mapped
    // Once we add trxn_id then ->has('transactionID') will be TRUE and the set won't be run.
    if (!$propertyBag->has('transactionID') && $propertyBag->has('trxn_id')) {
      $propertyBag->setTransactionID($propertyBag->getCustomProperty('trxn_id'));
    }

    return $propertyBag;
  }

  /**
   * Call this at the beginning of call to CRM_Core_Payment::changeSubscriptionAmount()
   * to ensure that all necessary parameters are set.
   *
   * @param array $params
   *
   * @return \Civi\Payment\PropertyBag
   */
  protected function beginChangeSubscriptionAmount(array $params): PropertyBag {
    /*
     * 5.43 passes an array of params as follows:
     * $params = [
     *   'amount' => '10.00',
     *   'currency' => 'USD',
     *   'id' => 10 // Needs to map to contributionRecurID
     *   'subscriptionId' => 'yxz3432' // The processor_id/trxn_id
     *   'installments' => '' // May be set or not
     * ];
     */
    $propertyBag = PropertyBag::cast($params);
    if (!$propertyBag->has('contributionRecurID')) {
      if (!empty($params['id'])) {
        $propertyBag->setContributionRecurID($params['id']);
      }
      else {
        throw new PaymentProcessorException('You MUST pass contributionRecurID (or id) to changeSubscriptionAmount$params');
      }
    }

    $propertyBag->setIsRecur(TRUE);

    $existingRecur = ContributionRecur::get(FALSE)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->addWhere('id', '=', $propertyBag->getContributionRecurID())
      ->execute()
      ->first();

    if (!$propertyBag->has('recurProcessorID')) {
      $propertyBag->setRecurProcessorID($existingRecur['processor_id']);
    }
    $propertyBag->setRecurInstallments($existingRecur['installments'] ?? 0);
    $propertyBag->setRecurFrequencyInterval($existingRecur['frequency_interval']);
    $propertyBag->setRecurFrequencyUnit($existingRecur['frequency_unit']);
    $propertyBag->setCurrency($params['currency'] ?? $existingRecur['currency']);

    $propertyBag->setCustomProperty('error_url', $this->getErrorUrl($propertyBag));

    // Make sure we have a contactID set on propertyBag
    $this->getContactId($propertyBag);
    return $propertyBag;
  }

  /**
   * Call this at the beginning of call to CRM_Core_Payment::updateSubscriptionBillingInfo()
   * to ensure that all necessary parameters are set.
   *
   * @param array $params
   *
   * @return \Civi\Payment\PropertyBag
   */
  protected function beginUpdateSubscriptionBillingInfo(array $params): PropertyBag {
    /*
     * 5.43 passes an array of params as follows:
     * $params = [
     *   'amount' => '10.00',
     *   'subscriptionId' => 'yxz3432' // The processor_id/trxn_id
     *   .. card/billing fields
     * ];
     */
    $propertyBag = PropertyBag::cast($params);
    $propertyBag->setIsRecur(TRUE);
    $whereAnd[] = ['is_test', 'IN', [TRUE, FALSE]];
    if (isset($params['id'])) {
      $propertyBag->setContributionRecurID($params['id']);
      $whereAnd[] = ['id', '=', $params['id']];
    }
    elseif (isset($params['subscriptionId'])) {
      $whereAnd[] = ['processor_id', '=', $params['subscriptionId']];
    }
    else {
      throw new PaymentProcessorException('Missing id or processor_id required to find contributionRecur');
    }

    $existingRecur = ContributionRecur::get(FALSE)
      ->setWhere([['AND', $whereAnd]])
      ->execute()
      ->first();

    if ($existingRecur) {
      $propertyBag->setContributionRecurID($existingRecur['id']);
    }

    // $propertyBag->setBillingCity($params['city'] ?? '');
    // @fixme Country: https://github.com/civicrm/civicrm-core/pull/28926 (5.71)
    if (!$propertyBag->has('billingCountry') && $propertyBag->has('country')) {
      $propertyBag->setBillingCountry($propertyBag->getCustomProperty('country'));
    }
    // $propertyBag->setBillingCountry($params['billingCountry'] ?? $params['country'] ?? '');
    // $propertyBag->setBillingStateProvince()

    $propertyBag->setCustomProperty('error_url', $this->getErrorUrl($propertyBag));

    // Make sure we have a contactID set on propertyBag
    $this->getContactId($propertyBag);
    return $propertyBag;
  }

  /**
   * Call this at the end of a call to CRM_Core_Payment::doPayment() to build the
   * standard return parameters array.
   *
   * @param \Civi\Payment\PropertyBag|array $params
   *
   * @return array
   * @throws \CRM_Core_Exception
   */
  protected function endDoPayment($params) {
    $propertyBag = PropertyBag::cast($params);

    // We need to set this to ensure that contributions are set to the correct status
    // It should have already been set to "Completed" if we made a successful payment
    if (!$propertyBag->has('payment_status_id')) {
      $propertyBag = $this->setStatusPaymentPending($propertyBag);
    }

    // payment_status is the newer property. It *should* be set but we'll make sure.
    if (!$propertyBag->has('payment_status')) {
      CRM_Core_Error::deprecatedWarning('endDoPayment payment_status is not set! Make sure you are using setStatusPaymentPending/Completed');
      if ($propertyBag->getCustomProperty('payment_status_id') == CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed')) {
        $propertyBag->setCustomProperty('payment_status', 'Completed');
      }
      else {
        $propertyBag->setCustomProperty('payment_status', 'Pending');
      }
    }

    // See https://lab.civicrm.org/dev/financial/-/issues/141
    $returnParams = [
      'payment_status_id' => $propertyBag->getCustomProperty('payment_status_id'),
      'payment_status' => $propertyBag->getCustomProperty('payment_status'),
      'trxn_id' => $this->getPaymentProcessorTrxnID() ?? $this->getPaymentProcessorOrderID(),
      'order_reference' => $this->getPaymentProcessorOrderID() ?? NULL,
    ];
    if ($propertyBag->has('feeAmount')) {
      $returnParams['fee_amount'] = $propertyBag->getFeeAmount();
    }
    return $returnParams;
  }

  /**
   * Set the payment status to Pending
   * @param \Civi\Payment\PropertyBag|array $params
   *
   * @return array
   */
  protected function setStatusPaymentPending($params) {
    $params['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $params['payment_status'] = 'Pending';
    return $params;
  }

  /**
   * Set the payment status to Completed
   * @param \Civi\Payment\PropertyBag|array $params
   *
   * @return array
   */
  protected function setStatusPaymentCompleted($params) {
    $params['payment_status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $params['payment_status'] = 'Completed';
    return $params;
  }

  /**
   * Get a "token" parameter that was inserted via javascript on the payment form (eg. paymentIntentID).
   *
   * @param string $parameterName
   * @param \Civi\Payment\PropertyBag $propertyBag
   * @param bool $required
   *
   * @return \Civi\Payment\PropertyBag
   * @throws \CRM_Core_Exception
   */
  protected function getTokenParameter($parameterName, $propertyBag, $required = TRUE) {
    // If we stored it via pre_approval_parameters it should already be in the params array
    if ($propertyBag->has($parameterName)) {
      return $propertyBag;
    }
    // If we're submitting without a confirmation page it should be in the $_POST array
    $parameterValue = CRM_Utils_Request::retrieve($parameterName, 'String');
    $propertyBag->setCustomProperty($parameterName, $parameterValue);

    if (empty($parameterValue) && $required) {
      Civi::log()->debug("{$parameterName} not found. \$params: " . print_r($propertyBag, TRUE));
      CRM_Core_Error::statusBounce(E::ts('Unable to complete payment! Missing %1.', [1 => $parameterName]));
    }
    return $propertyBag;
  }

  /**
   * This converts from \Civi\Payment\PropertyBag to array
   * It can be called on a propertyBag or an array.
   * @see https://github.com/civicrm/civicrm-core/pull/17507
   *
   * @param array|\Civi\Payment\PropertyBag $propertyBag
   * @param string $label
   *
   * @return mixed
   * @throws \ReflectionException
   */
  public function getPropertyBagAsArray($propertyBag, $label = 'default') {
    if ($propertyBag instanceof PropertyBag) {
      $reflectionClass = new ReflectionClass($propertyBag);
      $reflectionProperty = $reflectionClass->getProperty('props');
      $reflectionProperty->setAccessible(TRUE);
      $params = $reflectionProperty->getValue($propertyBag)['default'];
    }
    else {
      $params = $propertyBag;
    }
    return $params;
  }

}
