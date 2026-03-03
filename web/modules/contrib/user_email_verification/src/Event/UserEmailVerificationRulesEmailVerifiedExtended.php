<?php

namespace Drupal\user_email_verification\Event;

/**
 * Event: User email was verified (extended period).
 */
class UserEmailVerificationRulesEmailVerifiedExtended extends UserEmailVerificationBaseEvent {

  const EVENT_NAME = 'user_email_verification_rules_email_verified_extended';

}
