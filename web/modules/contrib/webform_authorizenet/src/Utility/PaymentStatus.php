<?php

namespace Drupal\webform_authorizenet\Utility;

/**
 * Defines constants for payment statuses.
 */
class PaymentStatus {

  /**
   * The key that indicating pending status.
   *
   * @var string
   */
  const PENDING = 'pending';

  /**
   * The key that indicating success status.
   *
   * @var string
   */
  const SUCCESS = 'success';

  /**
   * The key that indicating complete status.
   *
   * @var string
   */
  const COMPLETE = 'complete';

  /**
   * The key that indicating cancelled status.
   *
   * @var string
   */
  const CANCELLED = 'cancelled';

}
