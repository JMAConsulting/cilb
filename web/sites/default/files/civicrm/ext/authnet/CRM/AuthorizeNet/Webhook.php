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

use Civi\Api4\PaymentProcessor;
use CRM_AuthNetEcheck_ExtensionUtil as E;
use \Authnetjson\AuthnetApiFactory as AuthnetApiFactory;

class CRM_AuthorizeNet_Webhook {

  use CRM_Core_Payment_MJWTrait;

  /**
   * Payment processor
   *
   * @var array
   */
  private array $_paymentProcessor;

  /**
   * CRM_AuthorizeNet_Webhook constructor.
   *
   * @param array $paymentProcessor
   */
  function __construct(array $paymentProcessor) {
    $this->_paymentProcessor = $paymentProcessor;
  }

  /**
   * Get a request handler for authnet webhooks
   *
   * @return \Authnetjson\AuthnetWebhooksRequest
   * @throws \Authnetjson\Exception\AuthnetInvalidCredentialsException
   * @throws \Authnetjson\Exception\AuthnetInvalidServerException
   */
  public function getRequest(): \Authnetjson\AuthnetWebhooksRequest {
    return AuthnetApiFactory::getWebhooksHandler(
      CRM_Core_Payment_AuthorizeNetCommon::getApiLoginId($this->_paymentProcessor),
      CRM_Core_Payment_AuthorizeNetCommon::getTransactionKey($this->_paymentProcessor),
      $this->getIsTestMode() ? AuthnetApiFactory::USE_DEVELOPMENT_SERVER : AuthnetApiFactory::USE_PRODUCTION_SERVER);
  }

  /**
   * Get a list of configured webhooks
   *
   * @return \Authnetjson\AuthnetWebhooksResponse
   * @throws \Authnetjson\Exception\AuthnetCurlException
   * @throws \Authnetjson\Exception\AuthnetInvalidCredentialsException
   * @throws \Authnetjson\Exception\AuthnetInvalidJsonException
   * @throws \Authnetjson\Exception\AuthnetInvalidServerException
   */
  public function getWebhooks(): \Authnetjson\AuthnetWebhooksResponse {
    $request = $this->getRequest();
    return $request->getWebhooks();
  }

  /**
   * Create a new webhook
   *
   * @throws \ErrorException
   * @throws \Authnetjson\Exception\AuthnetCurlException
   * @throws \Authnetjson\Exception\AuthnetInvalidCredentialsException
   * @throws \Authnetjson\Exception\AuthnetInvalidJsonException
   * @throws \Authnetjson\Exception\AuthnetInvalidServerException
   */
  public function createWebhook(): void {
    $request = $this->getRequest();
    $request->createWebhooks(self::getDefaultEnabledEvents(), CRM_Mjwshared_Webhook::getWebhookPath($this->_paymentProcessor['id']), 'active');
  }

  /**
   * Check and update existing webhook
   *
   * @param array $webhook
   */
  /**
   * @param \Authnetjson\AuthnetWebhooksResponse $webhook
   *
   * @throws \Authnetjson\Exception\AuthnetCurlException
   * @throws \Authnetjson\Exception\AuthnetInvalidCredentialsException
   * @throws \Authnetjson\Exception\AuthnetInvalidJsonException
   * @throws \Authnetjson\Exception\AuthnetInvalidServerException
   */
  public function checkAndUpdateWebhook(\Authnetjson\AuthnetWebhooksResponse $webhook): void {
    $update = FALSE;
    if ($webhook->getStatus() !== 'active') {
      $update = TRUE;
    }
    if (array_diff(self::getDefaultEnabledEvents(), $webhook->getEventTypes())) {
      $update = TRUE;
    }
    if ($update) {
      $request = $this->getRequest();
      $request->updateWebhook($webhook->getWebhooksId(), CRM_Mjwshared_Webhook::getWebhookPath($this->_paymentProcessor['id']), self::getDefaultEnabledEvents(),'active');
    }
  }

  /**
   * Checks whether the payment processors have a correctly configured
   * webhook (we may want to check the test processors too, at some point, but
   * for now, avoid having false alerts that will annoy people).
   *
   * @see hook_civicrm_check()
   *
   * @param array $messages
   * @param bool $attemptFix
   *
   * @throws \CRM_Core_Exception
   * @throws \Authnetjson\Exception\AuthnetInvalidJsonException
   */
  public static function check(array &$messages, bool $attemptFix = FALSE): void {
    $env = Civi::settings()->get('environment');
    if ($env && $env !== 'Production') {
      return;
    }
    $checkMessage = [
      'name' => 'authnet_webhook',
      'label' => 'AuthorizeNet',
    ];
    $authNetPaymentProcessors = PaymentProcessor::get(FALSE)
      ->addWhere('payment_processor_type_id:name', 'IN', ['AuthNetAcceptjs', 'AuthorizeNetCreditcard', 'AuthorizeNeteCheck'])
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_test', 'IN', [TRUE, FALSE])
      ->execute();

    foreach ($authNetPaymentProcessors as $paymentProcessor) {
      if (empty($paymentProcessor['user_name']) || $paymentProcessor['user_name'] == CRM_Core_Payment_AuthorizeNetCommon::AUTHNETECHECK_SKIP_WEBHOOK_CHECKS) {
        continue;
      }
      $webhook_path = CRM_Mjwshared_Webhook::getWebhookPath($paymentProcessor['id']);

      try {
        $webhookHandler = new CRM_AuthorizeNet_Webhook($paymentProcessor);
        $webhooks = $webhookHandler->getWebhooks();
      }
      catch (Exception $e) {
        $error = $e->getMessage();
        $messages[] = new CRM_Utils_Check_Message(
          "{$checkMessage['name']}_webhook",
          E::ts('The %1 (%2) Payment Processor has an error: %3', [
            1 => $paymentProcessor['name'],
            2 => $paymentProcessor['id'],
            3 => $error,
          ]),
          "{$checkMessage['label']} " . E::ts('API Key: %1 (%2)', [
            1 => $paymentProcessor['name'],
            2 => $paymentProcessor['id'],
          ]),
          \Psr\Log\LogLevel::ERROR,
          'fa-money'
        );

        continue;
      }

      $foundWebhook = FALSE;
      foreach ($webhooks->getWebhooks() as $webhook) {
        try {
          if ($webhook->getURL() == $webhook_path) {
            $foundWebhook = TRUE;
            // Check and update webhook
            $webhookHandler->checkAndUpdateWebhook($webhook);
          }
        }
        catch (Exception $e) {
          $messages[] = new CRM_Utils_Check_Message(
            "{$checkMessage['name']}_webhook",
            E::ts('Could not update webhook. You can review from your account dashboard.<br/>The webhook URL is: %3', [
              1 => $paymentProcessor['name'],
              2 => $paymentProcessor['id'],
              3 => urldecode($webhook_path),
            ]) . ".<br/>Error from {$checkMessage['label']}: <em>" . $e->getMessage() . '</em>',
            "{$checkMessage['label']} " . E::ts('Webhook: %1 (%2)', [
                1 => $paymentProcessor['name'],
                2 => $paymentProcessor['id'],
              ]
            ),
            \Psr\Log\LogLevel::WARNING,
            'fa-money'
          );
        }
      }

      if (!$foundWebhook) {
        if ($attemptFix) {
          try {
            $webhookHandler->createWebhook();
          }
          catch (Exception $e) {
            $messages[] = new CRM_Utils_Check_Message(
              "{$checkMessage['name']}_webhook",
              E::ts('Could not create webhook. You can review from your account dashboard.<br/>The webhook URL is: %3', [
                1 => $paymentProcessor['name'],
                2 => $paymentProcessor['id'],
                3 => urldecode($webhook_path),
              ]) . ".<br/>Error from {$checkMessage['label']}: <em>" . $e->getMessage() . '</em>',
              "{$checkMessage['label']} " . E::ts('Webhook: %1 (%2)', [
                  1 => $paymentProcessor['name'],
                  2 => $paymentProcessor['id'],
                ]
              ),
              \Psr\Log\LogLevel::WARNING,
              'fa-money'
            );
          }
        }
        else {
          $message = new CRM_Utils_Check_Message(
            __FUNCTION__ . $paymentProcessor['id'] . "{$checkMessage['name']}_webhook",
            E::ts(
              "{$checkMessage['label']} Webhook missing or needs update! <em>Expected webhook path is: <a href='%1' target='_blank'>%1</a></em>",
              [1 => urldecode($webhook_path)]
            ),
            self::getTitle($paymentProcessor),
            \Psr\Log\LogLevel::WARNING,
            'fa-money'
          );
          $message->addAction(
            E::ts('View and fix problems'),
            NULL,
            'href',
            ['path' => 'civicrm/fix-authnet-webhook', 'query' => ['reset' => 1]]
          );
          $messages[] = $message;
        }
      }
    }
  }

  /**
   * Get the error message title for the system check
   *
   * @param array $paymentProcessor
   *
   * @return string
   */
  private static function getTitle(array $paymentProcessor): string {
    if (!empty($paymentProcessor['is_test'])) {
      $paymentProcessor['name'] .= ' (test)';
    }
    return E::ts('Authorize.net Payment Processor: %1 (%2)', [
      1 => $paymentProcessor['name'],
      2 => $paymentProcessor['id'],
    ]);
  }

  /**
   * List of webhooks we currently handle
   * @return array
   */
  public static function getDefaultEnabledEvents(): array {
    // See https://developer.authorize.net/api/reference/features/webhooks.html#Event_Types_and_Payloads
    return [
      'net.authorize.payment.authcapture.created', // Notifies you that an authorization and capture transaction was created.
      'net.authorize.payment.refund.created', // Notifies you that a successfully settled transaction was refunded.
      'net.authorize.payment.void.created', // Notifies you that an unsettled transaction was voided.

      //'net.authorize.customer.subscription.created', // Notifies you that a subscription was created.
      //'net.authorize.customer.subscription.updated', // Notifies you that a subscription was updated.
      //'net.authorize.customer.subscription.suspended',// Notifies you that a subscription was suspended.
      'net.authorize.customer.subscription.terminated',// Notifies you that a subscription was terminated.
      'net.authorize.customer.subscription.cancelled', // Notifies you that a subscription was cancelled.
      //'net.authorize.customer.subscription.expiring', // Notifies you when a subscription has only one recurrence left to be charged.

      'net.authorize.payment.fraud.held', // Notifies you that a transaction was held as suspicious.
      'net.authorize.payment.fraud.approved', // Notifies you that a previously held transaction was approved.
      'net.authorize.payment.fraud.declined', // Notifies you that a previously held transaction was declined.
    ];
  }

}
