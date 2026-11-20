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

use Civi\Api4\PaymentprocessorWebhook as PaymentprocessorWebhookEntity;

/**
 * Helpers for processors implementing PaymentProcessorWebhookInterface, to
 * avoid every consumer re-deriving the same dedup query.
 */
class PaymentProcessorWebhook {

  /**
   * Check whether a queued webhook event is a duplicate of one already
   * recorded under a lower ID, and if so mark it as an error.
   *
   * A webhook event can be delivered more than once (e.g. the processor
   * retries because it didn't get an ack in time). We keep every delivery
   * as its own PaymentprocessorWebhook row for troubleshooting, but only
   * the row with the lowest ID for a given event_id should be processed -
   * this ignores any duplicate with a higher ID, since a lower one exists.
   *
   * Call this from processWebhookEvent() before doing any real work:
   *
   *   if (\Civi\Mjwshared\PaymentProcessorWebhook::rejectIfDuplicate($webhookEvent)) {
   *     return FALSE;
   *   }
   *
   * @param array $webhookEvent
   *   The row passed in to processWebhookEvent().
   * @param string $message
   *   Message to store against the row if it is rejected as a duplicate.
   *
   * @return bool
   *   TRUE if this event was a duplicate (and has been marked as an error).
   *   FALSE if it is not a duplicate and should be processed as normal.
   */
  public static function rejectIfDuplicate(array $webhookEvent, string $message = 'Refusing to process this event as it is a duplicate.'): bool {
    $duplicates = PaymentprocessorWebhookEntity::get(FALSE)
      ->selectRowCount()
      ->addWhere('event_id', '=', $webhookEvent['event_id'])
      ->addWhere('id', '<', $webhookEvent['id'])
      ->execute()
      ->count();

    if (!$duplicates) {
      return FALSE;
    }

    PaymentprocessorWebhookEntity::update(FALSE)
      ->addWhere('id', '=', $webhookEvent['id'])
      ->addValue('status', 'error')
      ->addValue('message', $message)
      ->addValue('processed_date', 'now')
      ->execute();

    return TRUE;
  }

}
