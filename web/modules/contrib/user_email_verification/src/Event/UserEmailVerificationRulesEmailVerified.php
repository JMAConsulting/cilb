<?php

namespace Drupal\user_email_verification\Event;

/**
 * Event: User email was verified (standard period).
 */
class UserEmailVerificationRulesEmailVerified extends UserEmailVerificationBaseEvent {

  const EVENT_NAME = 'user_email_verification_rules_email_verified';

}
