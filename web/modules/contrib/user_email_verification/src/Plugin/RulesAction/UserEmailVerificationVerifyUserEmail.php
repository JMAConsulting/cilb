<?php

namespace Drupal\user_email_verification\Plugin\RulesAction;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesActionBase;
use Drupal\user\UserInterface;
use Drupal\user_email_verification\Event\UserEmailVerificationEvents;
use Drupal\user_email_verification\Event\UserEmailVerificationVerifyEvent;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides "Verify user email" action.
 *
 * @RulesAction(
 *   id = "user_email_verification_verify_user_email",
 *   label = @Translation("Verify user email"),
 *   category = @Translation("User email verification"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       description = @Translation("Specifies the user to verify email for.")
 *     ),
 *   }
 * )
 */
class UserEmailVerificationVerifyUserEmail extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * User email verification helper service.
   *
   * @var \Drupal\user_email_verification\UserEmailVerificationInterface
   */
  protected $userEmailVerification;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user_email_verification.service'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Constructs a UserEmailVerificationVerifyUserEmail object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user_email_verification\UserEmailVerificationInterface $user_email_verification_service
   *   User email verification helper service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserEmailVerificationInterface $user_email_verification_service, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->userEmailVerification = $user_email_verification_service;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Verify user email.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user object.
   */
  protected function doExecute(UserInterface $user) {
    $verification = $this->userEmailVerification->loadVerificationByUserId($user->id());

    // Email for requested user was already verified.
    if ($verification['verified']) {
      return;
    }

    $this->userEmailVerification->setEmailVerifiedByUserId($user->id());
    $event = new UserEmailVerificationVerifyEvent($user, $user->isBlocked());
    $this->eventDispatcher->dispatch($event, UserEmailVerificationEvents::VERIFY);

    // If the user is considered as blocked, notify the administrator.
    if ($event->notifyAsBlocked()) {
      $this->userEmailVerification->sendVerifyBlockedMail($user);
    }
  }

}
