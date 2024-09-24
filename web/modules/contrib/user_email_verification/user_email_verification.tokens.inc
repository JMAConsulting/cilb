<?php

/**
 * @file
 * Builds placeholder replacement tokens for user-related data.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\user\UserInterface;

/**
 * Implements hook_token_info().
 */
function user_email_verification_token_info() {
  $info['tokens']['user']['verify-email'] = [
    'name' => t('Verify-email URL'),
    'description' => t('The URL to verify the user Email.'),
  ];

  $info['tokens']['user']['verify-email-extended'] = [
    'name' => t('Verify-email extended URL'),
    'description' => t('The URL to verify the user Email after initial timeout period.'),
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function user_email_verification_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type == 'user' && isset($data['user']) && $data['user'] instanceof UserInterface) {
    /** @var \Drupal\user_email_verification\UserEmailVerificationInterface $service */
    $service = \Drupal::service('user_email_verification.service');

    foreach ($tokens as $name => $original) {
      switch ($name) {

        case 'verify-email':
          $replacements[$original] = $service->buildVerificationUrl($data['user'])->toString();
          break;

        case 'verify-email-extended':
          $replacements[$original] = $service->buildExtendedVerificationUrl($data['user'])->toString();
          break;
      }
    }
  }

  return $replacements;
}
