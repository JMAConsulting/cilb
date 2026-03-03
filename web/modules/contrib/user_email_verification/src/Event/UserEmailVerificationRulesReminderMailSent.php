<?php

namespace Drupal\user_email_verification\Event;

/**
 * Event: Reminder mail: Verify your email was sent.
 */
class UserEmailVerificationRulesReminderMailSent extends UserEmailVerificationBaseEvent {

  const EVENT_NAME = 'user_email_verification_rules_reminder_mail_sent';

}
