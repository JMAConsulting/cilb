<?php

namespace Drupal\user_email_verification\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user\UserInterface;
use Drupal\user_email_verification\Event\UserEmailVerificationEvents;
use Drupal\user_email_verification\Event\UserEmailVerificationRulesEmailVerifiedExtended;
use Drupal\user_email_verification\Event\UserEmailVerificationVerifyEvent;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Email verificationVerify controller.
 */
class UserEmailVerificationVerifyExtended extends ControllerBase implements ContainerInjectionInterface {

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
    $extended_timeout = $this->userEmailVerification->getExtendedValidateInterval();
    $current = $this->time->getRequestTime();

    if ($current - $timestamp > $extended_timeout) {
      $this->messenger()->addError($this->t('Your verify link has been expired and account has been deleted. Register a new one, or contact site administration.'));
      return $this->redirect('<front>');
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
      $this->eventDispatcher->dispatch($event, UserEmailVerificationEvents::VERIFY_EXTENDED);

      $rules_event = new UserEmailVerificationRulesEmailVerifiedExtended($user);
      $this->eventDispatcher->dispatch($rules_event, $rules_event::EVENT_NAME);

      // Activate blocked account if decided to un-block.
      if ($event->notifyAsBlocked()) {
        $user->activate();
        $user->save();

        $this->messenger()->addStatus($this->t('Your account is activated.'));
      }

      if ($this->currentUser()->isAuthenticated()) {
        return $this->redirect('entity.user.canonical', ['user' => $this->currentUser()->id()]);
      }
      else {
        return $this->redirect('<front>');
      }
    }

    $this->messenger()->addError($this->t('Your verification link is incorrect. Request a new one using the form below.'));
    return $this->redirect('user_email_verification.request');
  }

}
