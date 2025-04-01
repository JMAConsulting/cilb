<?php

namespace Drupal\user_email_verification\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * Wraps a user account delete event for event subscribers.
 *
 * @ingroup user_email_verification
 */
class UserEmailVerificationDeleteAccountEvent extends Event {

  /**
   * The user account being verify.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

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
   *   The user account being verify.
   * @param bool $should_be_deleted
   *   Should the user account be deleted or no.
   */
  public function __construct(UserInterface $user, $should_be_deleted) {
    $this->user = $user;
    $this->shouldBeDeleted = $should_be_deleted;
  }

  /**
   * Get the user account being verify.
   *
   * @return \Drupal\user\UserInterface
   *   The user account.
   */
  public function getUser() {
    return $this->user;
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
