<?php

namespace Drupal\user_email_verification\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\user\UserInterface;

/**
 * Event: User email was verified (standard period).
 */
class UserEmailVerificationRulesEmailVerified extends Event {

  const EVENT_NAME = 'user_email_verification_rules_email_verified';

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * Constructs the event object.
   *
   * @param \Drupal\user\UserInterface $account
   *   The account of a related user.
   */
  public function __construct(UserInterface $account) {
    $this->account = $account;
  }

}
