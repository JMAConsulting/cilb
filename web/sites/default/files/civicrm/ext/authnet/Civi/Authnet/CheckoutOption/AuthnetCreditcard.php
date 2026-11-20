<?php

namespace Civi\Authnet\CheckoutOption;

use Civi\Checkout\CheckoutOptionUtils;
use Civi\Checkout\CheckoutSession;
use Civi\Mjwshared\Checkout\BillingUtils;
use CRM_AuthNetEcheck_ExtensionUtil as E;

class AuthnetCreditcard extends AuthnetCheckoutOptionBase {

  /**
   * @inheritdoc
   */
  public function getFrontendLabel(): string {
    return E::ts('%1 (Authorize.net Credit Card)', [1 => $this->getConnectionDetails()['frontend_title']]);
  }

  /**
   * @inheritdoc
   */
  public function getLabel(): string {
    return E::ts('%1 (Authorize.net Credit Card)', [1 => $this->getConnectionDetails()['title']]);
  }

  public function getPaymentMethod(): string {
    return 'authnet_creditcard';
  }

  public function getQuickformProcessor(bool $testMode = FALSE): \CRM_Core_Payment_AuthorizeNetCommon {
    $paymentProcessorDetails = $this->getConnectionDetails($testMode);
    $paymentProcessor = new \CRM_Core_Payment_AuthNetCreditcard('', $paymentProcessorDetails);
    return $paymentProcessor;
  }

  public function getAfformSettings(bool $testMode): array {
    $fields = CheckoutOptionUtils::mapQuickformFieldMetadata(
      array_intersect_key($this->getQuickformProcessor()->getPaymentFormFieldsMetadata(), array_flip($this->getQuickformProcessor()->getPaymentFormFields()))
    );
    return [
      'fields' => $fields,
    ];
  }

  /**
   * @inheritdoc
   */
  public function startCheckout(CheckoutSession $session): void {
    $params = $session->getCheckoutParams();

    // FIXME: copy doPayment implementation and switch to Api4 style keys
    $whatOldStyleKeysDoesDoPaymentNeed = ['amount', 'contributionID', 'contactID', 'currency'];
    $params += CheckoutOptionUtils::fetchRequiredParams($session->getContributionId(), ['address_id'], $whatOldStyleKeysDoesDoPaymentNeed, $params);
    $params = CheckoutOptionUtils::mapCardParams($params);
    $params += BillingUtils::getBillingAddress($params['address_id'], $params['contactID']);
    $params += BillingUtils::getBillingName($params['contactID']);
    if (empty($params['email'])) {
      $params['email'] = BillingUtils::getBillingEmail($params['contactID']);
    }

    $payment = $this->getQuickformProcessor($session->isTestMode())->doPayment($params);

    // check payment status
    switch ($payment['payment_status']) {
      case 'Completed':
        // Payment was approved / will go through
        // key details are passed back to the CheckoutSession
        $session->setTransactionId($payment['trxn_id']);
        $session->setOrderReference($payment['order_reference']);

        $this->createPayment($session);
        $session->success();
        // ensure clientside messages are shown
        $session->setResponseItem('message', $session->getStatusMessage());
        return;

      case 'Pending':
        // Payment wasn't rejected, but wasn't approved either
        $session->pending();
        // ensure clientside messages are shown
        $session->setResponseItem('message', $session->getStatusMessage());
        return;
    }
  }

  /**
   * @inheritdoc
   */
  public function continueCheckout(CheckoutSession $session): void {
    // everything happens in the first submit, so nothing more to do here
  }

  /**
   * @inheritdoc
   *
   * Include card details when saving payment
   */
  public function createPayment(CheckoutSession $session): int {
    $panTruncation = substr($session->getCheckoutParam('credit_card_number'), -4);

    return \Civi\Api4\Payment::create(FALSE)
      ->addValue('contribution_id', $session->getContributionId())
      ->addValue('total_amount', $session->getTotalAmount())
      ->addValue('fee_amount', $session->getFeeAmount())
      ->addValue('payment_processor_id', $this->getPaymentProcessorId($session->isTestMode()))
      ->addValue('trxn_date', date('Ymd H:i:s'))
      ->addValue('trxn_id', $session->getTransactionId())
      ->addValue('order_reference', $session->getOrderReference())
      ->addValue('payment_instrument_id:name', $this->getPaymentMethod())
      ->addValue('pan_truncation', $panTruncation)
      ->addValue('card_type_id:name', $session->getCheckoutParam('credit_card_type'))
      ->execute()->first()['id'];
  }

}
