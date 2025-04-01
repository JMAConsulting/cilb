<?php

namespace Drupal\user_email_verification\Plugin\Condition;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesConditionBase;
use Drupal\user\UserInterface;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'User email verification extended period exceeded' condition.
 *
 * @Condition(
 *   id = "user_email_verification_extended_period_exceeded",
 *   label = @Translation("User email verification extended period exceeded"),
 *   category = @Translation("User email verification"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       description = @Translation("Specifies the user account to check.")
 *     ),
 *   }
 * )
 */
class UserEmailVerificationExtendedPeriodExceeded extends RulesConditionBase implements ContainerFactoryPluginInterface {

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
   * Constructs a UserEmailVerificationExtendedPeriodExceeded object.
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
   * Check if user email verification extended period exceeded.
   *
   * @param \Drupal\user\UserInterface $user
   *   The account to check.
   *
   * @return bool
   *   TRUE if user email verification extended period exceeded.
   */
  protected function doEvaluate(UserInterface $user) {
    return !$this->userEmailVerification->isVerificationExtendedPeriodExceeded($user->id());
  }

}
