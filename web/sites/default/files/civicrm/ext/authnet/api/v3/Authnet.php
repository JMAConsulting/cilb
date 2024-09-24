<?php
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
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_authnet_get_transaction_details_spec(&$spec) {
  $spec['payment_processor_id']['title'] = ts("Payment processor ID");
  $spec['payment_processor_id']['required'] = 1;
  $spec['transaction_id']['title'] = ts("Authnet Transaction ID");
  $spec['transaction_id']['required'] = 1;
}

/**
 * Get the AuthorizeNet payment transaction details
 *
 * @param array $params
 *
 * @return array
 * @throws \CRM_Core_Exception
 * @throws \ErrorException
 * @throws \Authnetjson\Exception\AuthnetInvalidCredentialsException
 * @throws \Authnetjson\Exception\AuthnetInvalidServerException
 */
function civicrm_api3_authnet_get_transaction_details($params) {
  $paymentProcessor = \Civi\Payment\System::singleton()->getById($params['payment_processor_id'])->getPaymentProcessor();
  $request  = AuthnetApiFactory::getJsonApiHandler(CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($paymentProcessor), CRM_Core_Payment_AuthorizeNetCommon::getTransactionKey($paymentProcessor), AuthnetApiFactory::USE_DEVELOPMENT_SERVER);
  /** @var AuthnetWebhooksResponse $response */
  $response = $request->getTransactionDetailsRequest(['transId' => $params['transaction_id']]);

  $responseArray = json_decode($response->getRawResponse(), TRUE);

  if ($response->messages->resultCode === 'Ok') {
    return civicrm_api3_create_success($responseArray);
  }
}
