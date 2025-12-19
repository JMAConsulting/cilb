<?php

namespace Civi\Api4\Action\AuthnetUtilities;
use CRM_Authnet_ExtensionUtil as E;

/**
 * https://civicrm.org/licensing
 */

use \Authnetjson\AuthnetApiFactory as AuthnetApiFactory;
use \Authnetjson\AuthnetWebhooksResponse as AuthnetWebhooksResponse;

/**
 * Retry a IPN
 *
 */
class Retry extends \Civi\Api4\Generic\AbstractAction {

  /**
   * paymentProcessorId
   *
   * Payment processor ID
   * @required
   * @var int
   */
  protected $paymentProcessorId;

  /**
   *
   *
   * Authnet notification id
   *
   * @required
   * @var string
   */
  protected $notificationId;

  public function _run(\Civi\Api4\Generic\Result $result) {

    $paymentProcessor = \Civi\Payment\System::singleton()->getById($this->getPaymentProcessorId())->getPaymentProcessor();
    if ($paymentProcessor['is_test'] == 1) {
      $url = 'https://apitest.authorize.net/';
      $mode = 'test';
    }
    else {
      $url = 'https://api.authorize.net/';
      $mode = 'live';
    }
    $base64Secret = base64_encode(
      \CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($paymentProcessor) .
      ':' .
      \CRM_Core_Payment_AuthorizeNetCommon::getTransactionKey($paymentProcessor)
    );

    $notificationId = $this->getNotificationId();

    $endPoint = "{$url}/rest/v1/notifications/{$notificationId}";
    $client = new \GuzzleHttp\Client();
    $headers = [
      'headers' => [
        'Authorization' => "Basic {$base64Secret}",
        'Content-Type' => 'application/json',
      ],
    ];

    $response = $client->get($endPoint, $headers);
    $dataRaw = $response->getBody()->getContents();
    if (!$dataRaw) {
      throw new \API_Exception("Failed to get payload for notification id {$notificationId}");
    }
    $data = json_decode($dataRaw);
    $eventType = $data->eventType ?? NULL;

    $ppObject = new \CRM_Core_Payment_AuthNetCreditcard($mode, $paymentProcessor);
    $ipnClass = new \CRM_Core_Payment_AuthNetIPN($ppObject);
    $ipnClass->setData($dataRaw);
    $ipnClass->setEventType($eventType);
    $ipnClass->setEventId($notificationId);

    // We can't simply run onReceiveWebhook because we lack the http headers
    // that authorize sends, to the webhook will be declared "invalid"

    // Check if this has been inserted into the payment processor web hooks
    // table already.
    $webhook = \Civi\Api4\PaymentprocessorWebhook::get()
      ->addWhere('payment_processor_id', '=', $this->getPaymentProcessorId())
      ->addWhere('event_id', '=', $notificationId)
      ->execute()
      ->first();

    if (!empty($webhook)) {
      $result[] = "Already entered into webhooks table.";
    }
    else {
      $result[] = "Adding to webhooks table.";
      $webhook = \Civi\Api4\PaymentprocessorWebhook::create()
        ->addValue('payment_processor_id', $this->getPaymentProcessorId())
        ->addValue('trigger', $eventType)
        ->addValue('identifier', '')
        ->addValue('event_id', $notificationId)
        ->addValue('data', $dataRaw)
        ->execute()
        ->first();
    }

    $ipnClass->processQueuedWebhookEvent($webhook);

    $result[] = "Successfully retried notification";

  }
}
