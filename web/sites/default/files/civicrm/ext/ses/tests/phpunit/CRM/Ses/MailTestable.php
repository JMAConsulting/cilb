<?php

/**
 * Test double for CRM_Ses_Mail: shrinks the retry budget/delay so the
 * "retries exhausted" path can be exercised without waiting out the real
 * ~25s of exponential backoff.
 */
class CRM_Ses_MailTestable extends CRM_Ses_Mail {

  protected function getMaxSesRetries(): int {
    return 3;
  }

  protected function getSesRetryDelayMicroseconds(): int {
    return 1;
  }

}
