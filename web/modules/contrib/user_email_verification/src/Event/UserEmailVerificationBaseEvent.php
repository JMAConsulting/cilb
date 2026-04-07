<?php

namespace Drupal\user_email_verification\Event;

use Drupal\user\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base event for user email verification events.
 */
abstract class UserEmailVerificationBaseEvent extends Event {

  /**
   * The user account being handled.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Constructs a user email verification event object.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account being handled.
   */
  public function __construct(UserInterface $user) {
    $this->user = $user;
  }

  /**
   * Get the user account being handled.
   *
   * @return \Drupal\user\UserInterface
   *   The user account.
   */
  public function getUser() {
    return $this->user;
  }

}
