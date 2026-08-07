<?php

/**
 * Removes email addresses from the Amazon SES account-level suppression
 * list when they are taken off hold in CiviCRM.
 */
class CRM_Ses_SuppressionList {

  /**
   * True only when on_hold transitions from 1 to 0.
   *
   * @param mixed $oldOnHold
   * @param mixed $newOnHold
   */
  public static function shouldRemove($oldOnHold, $newOnHold): bool {
    return (int) $oldOnHold === 1 && (int) $newOnHold === 0;
  }

  /**
   * Calls SES DeleteSuppressedDestination when on_hold transitions from
   * 1 to 0 and the feature is enabled. SES failures are logged and
   * swallowed - they never block the caller.
   *
   * @param string $email
   * @param mixed $oldOnHold
   * @param mixed $newOnHold
   * @param bool $enabled
   * @param \Aws\SesV2\SesV2Client $client
   * @param callable|null $logError Injectable for testing; defaults to
   *   Civi::log('ses')->error(). Signature: function (string $message): void
   */
  public static function maybeRemove(
    string $email,
    $oldOnHold,
    $newOnHold,
    bool $enabled,
    \Aws\SesV2\SesV2Client $client,
    ?callable $logError = NULL
  ): void {
    if (!$enabled || !self::shouldRemove($oldOnHold, $newOnHold)) {
      return;
    }

    try {
      $client->deleteSuppressedDestination(['EmailAddress' => $email]);
    }
    catch (\Throwable $e) {
      $message = "SES: Failed to remove {$email} from suppression list: {$e->getMessage()}";
      if ($logError !== NULL) {
        $logError($message);
      }
      else {
        Civi::log('ses')->error($message);
      }
    }
  }

}
