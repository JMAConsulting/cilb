<?php

namespace Civi\Api4\Action\AuthnetUtilities;
use CRM_Authnet_ExtensionUtil as E;

/**
 * https://civicrm.org/licensing
 */

/**
 * Test API functions for AuthNet
 *
 */
use \Authnetjson\AuthnetApiFactory as AuthnetApiFactory;
use \Authnetjson\AuthnetWebhooksResponse as AuthnetWebhooksResponse;

/**
 * Authnet.get_transaction_details API specification
 *
 */


class GetTransactionDetails extends \Civi\Api4\Generic\AbstractAction {

  /**
   * paymentProcessorId
   *
   * Payment processor ID
   * @required
   * @var int
   */
  protected $paymentProcessorId;

  /**
   * transactionId
   *
   * Authnet Transaction ID
   *
   * @required
   * @var int
   */
  protected $transactionId;

  public function _run(\Civi\Api4\Generic\Result $result) {

    $paymentProcessor = \Civi\Payment\System::singleton()->getById($this->getPaymentProcessorId())->getPaymentProcessor();
    if ($paymentProcessor['is_test'] == 1) {
      $testingServer = AuthnetApiFactory::USE_DEVELOPMENT_SERVER;
    }
    else {
      $testingServer = FALSE;
    }
    $request  = AuthnetApiFactory::getJsonApiHandler(
      \CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($paymentProcessor),
      \CRM_Core_Payment_AuthorizeNetCommon::getTransactionKey($paymentProcessor),
      $testingServer
    );
    $response = $request->getTransactionDetailsRequest(['transId' => $this->getTransactionId()]);

    $responseArray = json_decode($response->getRawResponse(), TRUE);

    if ($response->messages->resultCode === 'Ok') {
      foreach ($responseArray as $line) {
        $result[] = $line;
      }
    }
  }
}
