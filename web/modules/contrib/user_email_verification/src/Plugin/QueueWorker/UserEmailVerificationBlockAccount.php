<?php

namespace Drupal\user_email_verification\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block user accounts which wasn't verified by cron.
 *
 * @QueueWorker(
 *   id = "user_email_verification_block_account",
 *   title = @Translation("User email verification: Block user accounts"),
 *   cron = {"time" = 30}
 * )
 */
class UserEmailVerificationBlockAccount extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * User email verification helper service.
   *
   * @var \Drupal\user_email_verification\UserEmailVerificationInterface
   */
  protected $userEmailVerification;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\user_email_verification\UserEmailVerificationInterface $user_email_verification_service
   *   User email verification helper service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserEmailVerificationInterface $user_email_verification_service, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->userEmailVerification = $user_email_verification_service;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user_email_verification.service'),
      $container->get('logger.factory')->get('user_email_verification')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {

    foreach ($data as $uid) {
      $this->userEmailVerification->blockUserAccountById($uid);
    }

    $this->logger->notice('Blocked users with IDs: %ids', ['%ids' => implode(', ', $data)]);
  }

}
