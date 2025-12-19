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

use Civi\Api4\PaymentprocessorWebhook;

/**
 * This job performs various housekeeping actions related to the Stripe payment processor
 *
 * @param array $params
 *
 * @return array
 *   API result array.
 * @throws CRM_Core_Exception
 */
function civicrm_api3_job_process_paymentprocessor_webhooks($params) {
  if ($params['delete_old'] !== 0 && !empty($params['delete_old'])) {
    // Delete all locally recorded webhooks that are older than 3 months
    $oldWebhooksCount = PaymentprocessorWebhook::get(FALSE)
      ->selectRowCount()
      ->addWhere('payment_processor_id.domain_id', '=', CRM_Core_Config::domainID())
      ->addWhere('created_date', '<', $params['delete_old'])
      ->execute()
      ->count();
    if (!empty($oldWebhooksCount)) {
      PaymentprocessorWebhook::delete(FALSE)
        ->addWhere('payment_processor_id.domain_id', '=', CRM_Core_Config::domainID())
        ->addWhere('created_date', '<', $params['delete_old'])
        ->execute();
    }
  }

  // Get the Webhook Events to process
  // This is domain specific (as entities such as membershipType are domain-specific we must process per-domain).
  $paymentProcessorWebhooks = PaymentprocessorWebhook::get(FALSE)
    ->addWhere('payment_processor_id.domain_id', '=', CRM_Core_Config::domainID());

  if (!empty($params['id'])) {
    // Allow to force processing of a single record
    $paymentProcessorWebhooks->addWhere('id', '=', $params['id']);
  }
  elseif (!empty($params['event_id'])) {
    $paymentProcessorWebhooks->addWhere('event_id', '=', $params['event_id']);
  }
  else {
    $paymentProcessorWebhooks
      ->addWhere('processed_date', 'IS NULL')
      ->addWhere('status', '=', 'new')
      ->setLimit($params['queue_limit']);
  }
  $paymentProcessorWebhooksResult = $paymentProcessorWebhooks->execute();

  $results = [
    'queue_count' => $paymentProcessorWebhooksResult->count(),
    'deleted' => $oldWebhooksCount ?? 0,
    'processed' => 0,
    'successes' => 0,
    'errors' => 0,
  ];
  $eventsToProcess = [];
  if ($results['queue_count'] > 0) {
    $eventsToProcess = $paymentProcessorWebhooksResult->column('id');
    PaymentprocessorWebhook::update(FALSE)
      ->addWhere('id', 'IN', $eventsToProcess)
      ->addValue('status', 'processing')
      ->execute();
  }

  // When should we stop processing?
  $timeLimit = $params['time_limit'] + microtime(TRUE);

  foreach ($paymentProcessorWebhooksResult as $webhookEvent) {
    $paymentProcessor = \Civi\Payment\System::singleton()
      ->getById($webhookEvent['payment_processor_id']);

    if (method_exists($paymentProcessor, 'processWebhookEvent')) {
      // Payment Processor extensions implementing processWebhookEvent() have responsibility to:
      //
      // - attempt to process the event.
      // - catch expected and not expected exceptions, and handle appropriately
      // - update the stored $webhookEvent to error|success, optionally providing a message.
      // - return TRUE for success, FALSE for error
      $eventResult = $paymentProcessor->processWebhookEvent($webhookEvent);
      $results[$eventResult ? 'successes' : 'errors']++;
    }
    else {
      \Civi::log('mjwshared')->warning('Not processing webhook event because payment processor does not implement processWebhookEvent. Details: ' . print_r($webhookEvent, TRUE));
    }

    $results['processed']++;
    if ($results['processed'] < $results['queue_count'] && microtime(TRUE) > $timeLimit) {
      $results['note'] = "Stopped processing as time limit exceeded.";
      // Release the 'processing' status for any that we did not complete.
      PaymentprocessorWebhook::update(FALSE)
        ->addWhere('id', 'IN', $eventsToProcess)
        ->addWhere('status', '=', 'processing')
        ->addValue('status', 'new')
        ->execute();
      break;
    }
  }

  return civicrm_api3_create_success($results, $params);
}

/**
 * Action Payment.
 *
 * @param array $params
 */
function _civicrm_api3_job_process_paymentprocessor_webhooks_spec(&$params) {
  $params['delete_old']['api.default'] = '-3 month';
  $params['delete_old']['title'] = 'Delete old records after (default: -3 month)';
  $params['delete_old']['description'] = 'Delete old records from database. Specify 0 to disable. Default is "-3 month"';
  $params['delete_old']['type'] = CRM_Utils_Type::T_STRING;
  $params['id']['title'] = 'ID of PaymentprocessorWebhook record (for debugging)';
  $params['id']['description'] = 'Specify an ID to FORCE processing and ignore the state of the status/processed_date fields';
  $params['id']['type'] = CRM_Utils_TYPE::T_INT;
  $params['event_id'] = [
    'type' => CRM_Utils_Type::T_STRING,
    'title' => 'Event ID of PaymentprocessorWebhook record (for debugging)',
    'description' => 'Specify an Event ID to force processing of only that event (and ignore status/processed_date fields)'
  ];
  $params['time_limit'] = [
    'type' => CRM_Utils_TYPE::T_INT,
    'title' => 'Time limit (seconds)',
    'description' => 'After each event has been processed, we stop to see whether the time limit is exceeded, and stop if so. Useful if your cron is http initiated. Default 1 hour',
    'api.default' => 60*60,
  ];
  $params['queue_limit'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Queue limit (count)',
    'description' => 'Maximum number of webhook events to process each time this job runs. Too many events can cause memory issues and lock the database for too long. Default 1000',
    'api.default' => 1000,
  ];
}
