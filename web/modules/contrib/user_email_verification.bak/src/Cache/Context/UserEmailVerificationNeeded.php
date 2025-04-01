<?php

namespace Drupal\user_email_verification\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user_email_verification\UserEmailVerificationInterface;

/**
 * Defines the UserEmailVerificationNeeded context service.
 *
 * Cache context ID: 'user_email_verification_needed'.
 */
class UserEmailVerificationNeeded implements CacheContextInterface {

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
   * Constructs a new object.
   *
   * @param \Drupal\user_email_verification\UserEmailVerificationInterface $user_email_verification_service
   *   User email verification helper service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   */
  public function __construct(UserEmailVerificationInterface $user_email_verification_service, AccountProxyInterface $current_user) {
    $this->userEmailVerification = $user_email_verification_service;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('User Email verification needed');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->currentUser->isAuthenticated() && $this->userEmailVerification->isVerificationNeeded() ? 'uev-needed' : '';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($type = NULL) {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['user']);

    return $cacheability;
  }

}
