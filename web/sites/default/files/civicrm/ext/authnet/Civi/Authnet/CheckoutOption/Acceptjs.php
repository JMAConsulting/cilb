<?php

namespace Civi\Authnet\CheckoutOption;

use Civi\Checkout\CheckoutSession;
use Civi\Checkout\CheckoutOptionUtils;
use Civi\Mjwshared\Checkout\BillingUtils;
use CRM_AuthNetEcheck_ExtensionUtil as E;

class Acceptjs extends AuthnetCheckoutOptionBase {

  /**
   * @inheritdoc
   */
  public function getFrontendLabel(): string {
    return E::ts('%1 (Authorize.net Accept.js)', [1 => $this->getConnectionDetails()['frontend_title']]);
  }

  /**
   * @inheritdoc
   */
  public function getLabel(): string {
    return E::ts('%1 (Authorize.net Accept.js)', [1 => $this->getConnectionDetails()['title']]);
  }

  public function getAfformModule(): string {
    return 'afAuthnet';
  }

  public function getPaymentMethod(): string {
    return 'accept_js';
  }

  /**
   * @param bool $testMode
   *
   * @return array
   */
  public function getAfformSettings(bool $testMode): array {
    return [
      'template' => '~/afAuthnet/afAcceptJs.html',
      'apiLoginId' => \CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($this->getConnectionDetails($testMode)),
      'clientKey' => \CRM_Core_Payment_AuthorizeNetCommon::getPublicClientKey($this->getConnectionDetails($testMode)),
      'clientScript' => $this->getClientScript($testMode),
    ];
  }

  protected function getClientScript(bool $testMode): string {
    // TODO: allow using sandbox credentials in the live slot vice verse?
    return $testMode ? 'https://jstest.authorize.net/v3/AcceptUI.js' : 'https://js.authorize.net/v3/AcceptUI.js';
  }

  public function startCheckout(CheckoutSession $session): void {
    $params = $session->getCheckoutParams();

    // FIXME: copy doPayment implementation and switch to Api4 style keys
    $whatOldStyleKeysDoesDoPaymentNeed = ['amount', 'contributionID', 'contactID', 'currency'];
    $params += CheckoutOptionUtils::fetchRequiredParams($session->getContributionId(), ['address_id'], $whatOldStyleKeysDoesDoPaymentNeed, $params);
    // Send the CiviCRM billing details with the transaction (billTo). Accept.js
    // otherwise only has whatever the hosted popup collected (name + zip).
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

        $session->createPayment();
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

  public function continueCheckout(CheckoutSession $session): void {
    // TODO: Implement continueCheckout() method.
  }

  /**
   * @inheritdoc
   *
   * Include card details when saving payment
   */
  public function createPayment(CheckoutSession $session): int {
    return \Civi\Api4\Payment::create(FALSE)
      ->addValue('contribution_id', $session->getContributionId())
      ->addValue('total_amount', $session->getTotalAmount())
      ->addValue('fee_amount', $session->getFeeAmount())
      ->addValue('payment_processor_id', $this->getPaymentProcessorId($session->isTestMode()))
      ->addValue('trxn_date', date('Ymd H:i:s'))
      ->addValue('trxn_id', $session->getTransactionId())
      ->addValue('order_reference', $session->getOrderReference())
      ->addValue('payment_instrument_id:name', $this->getPaymentMethod())
      ->execute()->first()['id'];
  }

}
