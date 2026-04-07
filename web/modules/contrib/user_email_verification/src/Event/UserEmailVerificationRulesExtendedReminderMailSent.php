<?php

namespace Drupal\user_email_verification\Event;

/**
 * Event: Reminder mail: Verify your email (extended period available) was sent.
 */
class UserEmailVerificationRulesExtendedReminderMailSent extends UserEmailVerificationBaseEvent {

  const EVENT_NAME = 'user_email_verification_rules_extended_reminder_mail_sent';

}
