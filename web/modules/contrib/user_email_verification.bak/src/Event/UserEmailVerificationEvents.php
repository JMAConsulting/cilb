<?php

namespace Drupal\user_email_verification\Event;

/**
 * Defines events for the user_email_verification module.
 *
 * @ingroup user_email_verification
 */
final class UserEmailVerificationEvents {

  /**
   * The event fired when user account is being verified.
   *
   * @Event
   *
   * @see \Drupal\user_email_verification\Event\UserEmailVerificationVerifyEvent
   *
   * @var string
   */
  const VERIFY = 'user_email_verification.verify';

  /**
   * The event fired when user account is being verified after extended period.
   *
   * @Event
   *
   * @see \Drupal\user_email_verification\Event\UserEmailVerificationVerifyEvent
   *
   * @var string
   */
  const VERIFY_EXTENDED = 'user_email_verification.verify_extended';

  /**
   * The event fired just before verification creation.
   *
   * @Event
   *
   * @see \Drupal\user_email_verification\Event\UserEmailVerificationCreateVerificationEvent
   *
   * @var string
   */
  const CREATE_VERIFICATION = 'user_email_verification.create_verification';

  /**
   * The event fired just before user account blocking.
   *
   * @Event
   *
   * @see \Drupal\user_email_verification\Event\UserEmailVerificationBlockAccountEvent
   *
   * @var string
   */
  const BLOCK_ACCOUNT = 'user_email_verification.block_account';

  /**
   * The event fired just before user account deletion.
   *
   * @Event
   *
   * @see \Drupal\user_email_verification\Event\UserEmailVerificationDeleteAccountEvent
   *
   * @var string
   */
  const DELETE_ACCOUNT = 'user_email_verification.delete_account';

}
