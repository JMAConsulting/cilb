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

/**
 * This job performs various housekeeping actions related to the Stripe payment processor
 *
 * @deprecated The real implementation is
 * Civi\Api4\Action\Job\ProcessPaymentprocessorWebhooks - call
 * Civi\Api4\Job::processPaymentprocessorWebhooks() directly instead. Kept as
 * a thin wrapper only for sites whose civicrm_job row still has the old
 * api_action ('process_paymentprocessor_webhooks') and delete_old/id/
 * event_id/time_limit/queue_limit parameter names - the managed entity now
 * declares the APIv4 action/params directly, but existing sites that have
 * customized this job's parameters won't get that applied automatically
 * (see managed/ProcessPaymentprocessorWebhooks.mgd.php, update: unmodified),
 * so this keeps working for them indefinitely.
 *
 * @param array $params
 *
 * @return array
 *   API result array.
 * @throws CRM_Core_Exception
 */
function civicrm_api3_job_process_paymentprocessor_webhooks($params) {
  $result = \Civi\Api4\Job::processPaymentprocessorWebhooks(FALSE)
    ->setDeleteOld($params['delete_old'] ?? '-3 month')
    ->setId($params['id'] ?? NULL)
    ->setEventId($params['event_id'] ?? NULL)
    ->setTimeLimit($params['time_limit'] ?? 3600)
    ->setQueueLimit($params['queue_limit'] ?? 1000)
    ->execute();

  return civicrm_api3_create_success($result->getArrayCopy(), $params);
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
    'description' => 'Specify an Event ID to force processing of only that event (and ignore status/processed_date fields)',
  ];
  $params['time_limit'] = [
    'type' => CRM_Utils_TYPE::T_INT,
    'title' => 'Time limit (seconds)',
    'description' => 'After each event has been processed, we stop to see whether the time limit is exceeded, and stop if so. Useful if your cron is http initiated. Default 1 hour',
    'api.default' => 60 * 60,
  ];
  $params['queue_limit'] = [
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Queue limit (count)',
    'description' => 'Maximum number of webhook events to process each time this job runs. Too many events can cause memory issues and lock the database for too long. Default 1000',
    'api.default' => 1000,
  ];
}
