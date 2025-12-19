<?php

namespace Civi\Api4\Action\AuthnetUtilities;
use CRM_Authnet_ExtensionUtil as E;

/**
 * https://civicrm.org/licensing
 */

use \Authnetjson\AuthnetApiFactory as AuthnetApiFactory;
use \Authnetjson\AuthnetWebhooksResponse as AuthnetWebhooksResponse;

/**
 * Authnet specified notification logs
 *
 */
class GetNotificationLogs extends \Civi\Api4\Generic\AbstractAction {

  /**
   * paymentProcessorId
   *
   * Payment processor ID
   * @required
   * @var int
   */
  protected $paymentProcessorId;

  /**
   * deliveryStatus
   *
   * Authnet delivery status: leave out for all notifications, or specify
   * Failed or RetryPending or Delivered
   *
   * @var string
   */
  protected $deliveryStatus;

  public function _run(\Civi\Api4\Generic\Result $result) {

    $paymentProcessor = \Civi\Payment\System::singleton()->getById($this->getPaymentProcessorId())->getPaymentProcessor();
    if ($paymentProcessor['is_test'] == 1) {
      $useTestingServer = AuthnetApiFactory::USE_DEVELOPMENT_SERVER;
      $url = 'https://apitest.authorize.net/';
    }
    else {
      $useTestingServer = FALSE;
      $url = 'https://api.authorize.net/';
    }
    $request  = AuthnetApiFactory::getWebhooksHandler(
      \CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($paymentProcessor),
      \CRM_Core_Payment_AuthorizeNetCommon::getTransactionKey($paymentProcessor),
      $useTestingServer
    );
    $response = $request->getNotificationHistory();
    $base64Secret = base64_encode(
      \CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($paymentProcessor) .
      ':' .
      \CRM_Core_Payment_AuthorizeNetCommon::getTransactionKey($paymentProcessor)
    );
    $filterDeliveryStatus = $this->getDeliveryStatus();
    foreach ($response->getNotificationHistory() as $notification) {
      $deliveryStatus = $notification->getDeliveryStatus();
      if ($filterDeliveryStatus && $filterDeliveryStatus != $deliveryStatus) {
        continue;
      }

      $notificationId = $notification->getNotificationId();

      // Now get the full notification details so we have access to the retries and
      // the payload. stymie library doesn't have this method.
      $endPoint = "{$url}/rest/v1/notifications/{$notificationId}";
      $client = new \GuzzleHttp\Client();
      $headers = [
        'headers' => [
          'Authorization' => "Basic {$base64Secret}",
          'Content-Type' => 'application/json',
        ],
      ];
      $response = $client->get($endPoint, $headers);
      $content = $response->getBody()->getContents();
      $data = json_decode($content, TRUE);

      $result[] = $data;

    }
  }
}
