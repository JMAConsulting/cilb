<?php

namespace Drupal\user_email_verification\Event;

use Drupal\user\UserInterface;

/**
 * Wraps a user account block event for event subscribers.
 *
 * @ingroup user_email_verification
 */
class UserEmailVerificationBlockAccountEvent extends UserEmailVerificationBaseEvent {

  /**
   * Should the user account be blocked or no.
   *
   * @var bool
   */
  protected $shouldBeBlocked;

  /**
   * Constructs a user email verification event object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account being verified.
   * @param bool $should_be_blocked
   *   Should the user account be blocked or no.
   */
  public function __construct(UserInterface $user, $should_be_blocked) {
    parent::__construct($user);
    $this->shouldBeBlocked = $should_be_blocked;
  }

  /**
   * Gets should the user account be blocked or no.
   *
   * @return bool
   *   Should the user account be blocked or no.
   */
  public function shouldBeBlocked() : bool {
    return $this->shouldBeBlocked;
  }

  /**
   * Sets should the user account be blocked or no.
   *
   * @param bool $should_be_blocked
   *   Should the user account be blocked or no.
   */
  public function setShouldBeBlocked($should_be_blocked) {
    $this->shouldBeBlocked = $should_be_blocked;
  }

}
