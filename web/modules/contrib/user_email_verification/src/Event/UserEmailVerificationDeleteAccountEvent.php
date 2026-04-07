<?php

namespace Drupal\user_email_verification\Event;

use Drupal\user\UserInterface;

/**
 * Wraps a user account delete event for event subscribers.
 *
 * @ingroup user_email_verification
 */
class UserEmailVerificationDeleteAccountEvent extends UserEmailVerificationBaseEvent {

  /**
   * Should the user account be deleted or no.
   *
   * @var bool
   */
  protected $shouldBeDeleted;

  /**
   * Constructs a user email verification event object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account being verified.
   * @param bool $should_be_deleted
   *   Should the user account be deleted or no.
   */
  public function __construct(UserInterface $user, $should_be_deleted) {
    parent::__construct($user);
    $this->shouldBeDeleted = $should_be_deleted;
  }

  /**
   * Gets should the user account be deleted or no.
   *
   * @return bool
   *   Should the user account be deleted or no.
   */
  public function shouldBeDeleted() : bool {
    return $this->shouldBeDeleted;
  }

  /**
   * Sets should the user account be deleted or no.
   *
   * @param bool $should_be_deleted
   *   Should the user account be deleted or no.
   */
  public function setShouldBeDeleted($should_be_deleted) {
    $this->shouldBeDeleted = $should_be_deleted;
  }

}
