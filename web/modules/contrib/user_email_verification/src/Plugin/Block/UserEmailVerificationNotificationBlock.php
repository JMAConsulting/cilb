<?php

namespace Drupal\user_email_verification\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block to display 'You have to verify your Email' notification.
 *
 * @Block(
 *   id = "user_email_verification_notification",
 *   admin_label = @Translation("User Email verification notification"),
 * )
 */
class UserEmailVerificationNotificationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * User email verification helper service.
   *
   * @var \Drupal\user_email_verification\UserEmailVerificationInterface
   */
  protected $userEmailVerification;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

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
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserEmailVerificationInterface $user_email_verification_service, AccountProxyInterface $current_user, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->userEmailVerification = $user_email_verification_service;
    $this->currentUser = $current_user;
    $this->token = $token;
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
      $container->get('current_user'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'notification' => [
        'value' => $this->t('Verify your Email by 3 simple steps:<br>1. Open your mail inbox,<br>2. Find Email verification message from [site:name],<br>3. Click by verification link.'),
        'format' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['notification'] = [
      '#title' => $this->t('Notification message'),
      '#description' => $this->t('Paste here a message with instructions which will notify a user to verify Email.'),
      '#type' => 'text_format',
      '#required' => TRUE,
      '#default_value' => $this->configuration['notification']['value'],
      '#format' => $this->configuration['notification']['format'],
    ];

    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->setConfigurationValue('notification', $form_state->getValue('notification'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    if ($this->currentUser->isAuthenticated() && $this->userEmailVerification->isVerificationNeeded()) {
      $build = [
        '#attributes' => [
          'class' => ['email-verify-notification'],
        ],
        'notification' => [
          '#type' => 'processed_text',
          '#text' => $this->token->replace($this->configuration['notification']['value']),
          '#format' => $this->configuration['notification']['format'],
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      [
        'user:' . $this->currentUser->id(),
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(
      parent::getCacheContexts(),
      [
        'user_email_verification_needed',
      ]
    );
  }

}
