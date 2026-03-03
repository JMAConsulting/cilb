<?php

namespace Drupal\user_email_verification\Event;

use Drupal\user\UserInterface;

/**
 * Wraps a user email verification event for event subscribers.
 *
 * @ingroup user_email_verification
 */
class UserEmailVerificationVerifyEvent extends UserEmailVerificationBaseEvent {

  /**
   * Notify the user as blocked account.
   *
   * @var bool
   */
  protected $notifyAsBlocked;

  /**
   * Constructs a user email verification event object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account being verified.
   * @param bool $notify_as_blocked
   *   Whether to notify the user as blocked account.
   */
  public function __construct(UserInterface $user, $notify_as_blocked = FALSE) {
    parent::__construct($user);
    $this->notifyAsBlocked = $notify_as_blocked;
  }

  /**
   * Gets whether if the user must be notified as blocked account.
   *
   * @return bool
   *   The user account.
   */
  public function notifyAsBlocked() : bool {
    return $this->notifyAsBlocked;
  }

  /**
   * Sets whether if the user must be notified as blocked account.
   *
   * @param bool $notify_as_blocked
   *   Whether the user must be notified as blocked account.
   */
  public function setNotifyAsBlocked($notify_as_blocked) {
    $this->notifyAsBlocked = $notify_as_blocked;
  }

}
