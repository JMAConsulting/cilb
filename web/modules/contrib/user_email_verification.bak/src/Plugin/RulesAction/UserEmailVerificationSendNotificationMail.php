<?php

namespace Drupal\user_email_verification\Plugin\RulesAction;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesActionBase;
use Drupal\user\UserInterface;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides "Send notification mail" action.
 *
 * @RulesAction(
 *   id = "user_email_verification_send_notification_mail",
 *   label = @Translation("Send notification mail"),
 *   category = @Translation("User email verification"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       description = @Translation("Specifies the user to verify email for.")
 *     ),
 *   }
 * )
 */
class UserEmailVerificationSendNotificationMail extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * User email verification helper service.
   *
   * @var \Drupal\user_email_verification\UserEmailVerificationInterface
   */
  protected $userEmailVerification;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user_email_verification.service')
    );
  }

  /**
   * Constructs a UserEmailVerificationSendNotificationMail object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user_email_verification\UserEmailVerificationInterface $user_email_verification_service
   *   User email verification helper service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserEmailVerificationInterface $user_email_verification_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->userEmailVerification = $user_email_verification_service;
  }

  /**
   * Send notification mail.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user object.
   */
  protected function doExecute(UserInterface $user) {
    $this->userEmailVerification->remindUserById($user->id());
  }

}
