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

namespace Civi\Mjwshared;

/**
 * Contract for payment processors that receive webhooks via mjwshared's
 * PaymentprocessorWebhook queue.
 *
 * Implement this on the CRM_Core_Payment subclass (not a helper/IPN class),
 * since it is that instance which core's IPN dispatcher
 * (CRM_Core_Payment::handlePaymentMethod()) and the
 * Job.process_paymentprocessor_webhooks scheduled job both call.
 */
interface PaymentProcessorWebhookInterface {

  /**
   * Handle an inbound webhook HTTP request.
   *
   * Called by CRM_Core_Payment::handleIPN() for requests to
   * civicrm/payment/ipn/<processor_id>. Implementations are responsible for
   * verifying the request's authenticity, parsing it, emitting the HTTP
   * response code, and either processing the event immediately or queuing
   * it as a PaymentprocessorWebhook row for the scheduled job to pick up.
   *
   * This method must always return control to the caller rather than
   * calling CRM_Utils_System::civiExit()/exit()/die() itself - core's
   * handleIPN() is responsible for firing the postIPNProcess hook and
   * exiting once all matching processor instances have run.
   */
  public function handlePaymentNotification(): void;

  /**
   * Process one previously-queued webhook event.
   *
   * Called by the Job.process_paymentprocessor_webhooks scheduled job for
   * each PaymentprocessorWebhook row with status 'new'. Implementations
   * must be idempotent (see PaymentProcessorWebhook::rejectIfDuplicate())
   * and are responsible for updating the row's status/message/
   * processed_date themselves - the job only flips new -> processing
   * before calling this, and handles releasing rows stuck in 'processing'
   * back to 'new' if the time limit is exceeded. Implementations have
   * responsibility to:
   *
   * - attempt to process the event.
   * - catch expected and unexpected exceptions, and handle appropriately.
   * - update the stored $webhookEvent to error|success, optionally
   *   providing a message.
   * - return TRUE for success, FALSE for error.
   *
   * @param array $webhookEvent
   *   The full PaymentprocessorWebhook row (id, payment_processor_id,
   *   event_id, trigger, identifier, data, ...).
   *
   * @return bool
   *   TRUE on success, FALSE on error.
   */
  public function processWebhookEvent(array $webhookEvent): bool;

}
