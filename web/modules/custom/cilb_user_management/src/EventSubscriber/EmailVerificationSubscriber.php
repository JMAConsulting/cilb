<?php

namespace Drupal\cilb_user_management\EventSubscriber;

use Drupal\user_email_verification\Event\UserEmailVerificationVerifyEvent;
use Drupal\user_email_verification\Event\UserEmailVerificationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EmailVerificationSubscriber
 *
 * @package Drupal\cilb_user_management\EventSubscriber
 */
class EmailVerificationSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      UserEmailVerificationEvents::VERIFY => 'verifyEvent',
    ];
  }

  public function verifyEvent(UserEmailVerificationVerifyEvent $event) {
    if (!$event->getUser()->isBlocked()) {
      $user = $event->getUser();
      // Trigger the password reset email now that the user has been verified.
      \Drupal::service('plugin.manager.mail')->mail(
        'user',
        'register_no_approval_required',
        $user->getEmail(),
        $user->getPreferredLangcode(),
        ['account' => $user],
        NULL,
        TRUE
      );
    }
  }

}
