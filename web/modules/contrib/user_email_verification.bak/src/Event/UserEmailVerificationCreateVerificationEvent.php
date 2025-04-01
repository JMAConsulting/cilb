<?php

namespace Drupal\user_email_verification\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * Wraps a user email verification creation event for event subscribers.
 *
 * @ingroup user_email_verification
 */
class UserEmailVerificationCreateVerificationEvent extends Event {

  /**
   * The user account being verify.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Should the user account be verified by default or no.
   *
   * @var bool
   */
  protected $shouldBeVerified;

  /**
   * Constructs a user email verification event object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account being verify.
   * @param bool $should_be_verified
   *   Should be user account verified by default or no.
   */
  public function __construct(UserInterface $user, $should_be_verified) {
    $this->user = $user;
    $this->shouldBeVerified = $should_be_verified;
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
   * Gets should the user account be verified by default or no.
   *
   * @return bool
   *   Should the user account be verified by default or no.
   */
  public function shouldBeVerified() : bool {
    return $this->shouldBeVerified;
  }

  /**
   * Sets should the user account be verified by default or no.
   *
   * @param bool $should_be_verified
   *   Should the user account be verified by default or no.
   */
  public function setShouldBeVerified($should_be_verified) {
    $this->shouldBeVerified = $should_be_verified;
  }

}
