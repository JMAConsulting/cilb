<?php

namespace Drupal\user_email_verification\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user\UserInterface;
use Drupal\user_email_verification\Event\UserEmailVerificationEvents;
use Drupal\user_email_verification\Event\UserEmailVerificationRulesEmailVerified;
use Drupal\user_email_verification\Event\UserEmailVerificationVerifyEvent;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Email verificationVerify controller.
 */
class UserEmailVerificationVerify extends ControllerBase implements ContainerInjectionInterface {

  /**
   * User email verification helper service.
   *
   * @var \Drupal\user_email_verification\UserEmailVerificationInterface
   */
  protected $userEmailVerification;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\user_email_verification\UserEmailVerificationInterface $user_email_verification_service
   *   User email verification helper service.
   * @param \Drupal\Component\Datetime\TimeInterface $datetime_time
   *   The time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(UserEmailVerificationInterface $user_email_verification_service, TimeInterface $datetime_time, EventDispatcherInterface $event_dispatcher) {
    $this->userEmailVerification = $user_email_verification_service;
    $this->time = $datetime_time;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user_email_verification.service'),
      $container->get('datetime.time'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Callback to handle user's Email verification.
   *
   * @param int $uid
   *   User ID to verify email for.
   * @param int $timestamp
   *   The timestamp when verification link was generated.
   * @param string $hashed_pass
   *   Hashed pass.
   */
  public function verify($uid, $timestamp, $hashed_pass) {

    $uid = (int) $uid;
    $timestamp = (int) $timestamp;
    $timeout = $this->userEmailVerification->getValidateInterval();
    $current = $this->time->getRequestTime();

    // User tries to use verification link that was expired.
    if ($current - $timestamp > $timeout) {
      $this->messenger()->addError($this->t('Your verification link has expired. Request a new one using the form below.'));
      return $this->redirect('user_email_verification.request');
    }

    $verification = $this->userEmailVerification->loadVerificationByUserId($uid);

    // User tries to use verification link that doesn't belong to him
    // or link was created for user which doesn't exist.
    if (($this->currentUser()->isAuthenticated() && $this->currentUser()->id() != $uid) || !$verification) {
      $this->messenger()->addError($this->t('Your verification link is incorrect. Request a new one using the form below.'));
      return $this->redirect('user_email_verification.request');
    }
    // Email for requested user was already verified.
    if ($verification['verified']) {
      $this->messenger()->addStatus($this->t('Email is already verified.'));
      return $this->redirect('<front>');
    }

    $user = $this->entityTypeManager()->getStorage('user')->load($uid);

    // User exists and requested hash is correct.
    if ($user instanceof UserInterface && $hashed_pass === $this->userEmailVerification->buildHmac($user->id(), $timestamp)) {

      $this->userEmailVerification->setEmailVerifiedByUserId($user->id());
      $this->messenger()->addStatus($this->t('Thank you for verifying your Email address.'));

      $event = new UserEmailVerificationVerifyEvent($user, $user->isBlocked());
      $this->eventDispatcher->dispatch($event, UserEmailVerificationEvents::VERIFY);

      $rules_event = new UserEmailVerificationRulesEmailVerified($user);
      $this->eventDispatcher->dispatch($rules_event, $rules_event::EVENT_NAME);

      // If the user is considered as blocked, notify the administrator and the
      // user. After it redirect to the front page.
      if ($event->notifyAsBlocked()) {
        $this->userEmailVerification->sendVerifyBlockedMail($user);
        $this->messenger()->addWarning($this->t('Your account has been blocked before the verification of the Email. An administrator will make an audit and unblock your account if the reason for the blocking was the Email verification.'));

        return $this->redirect('<front>');
      }
      // The user is already authenticated, redirect to the user profile.
      elseif ($this->currentUser()->isAuthenticated()) {
        return $this->redirect('entity.user.canonical', ['user' => $this->currentUser()->id()]);
      }
      // Otherwise redirect to the front page.
      else {
        return $this->redirect('<front>');
      }
    }

    $this->messenger()->addError($this->t('Your verification link is incorrect. Request a new one using the form below.'));
    return $this->redirect('user_email_verification.request');
  }

}
