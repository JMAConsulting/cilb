<?php

/**
 * @file
 * Builds placeholder replacement tokens for Webform Authorize.Net.
 */

use Drupal\webform_authorizenet\Utility\SubmissionHelper;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function webform_authorizenet_token_info() {
  $types['webform_submission'] = [
    'name' => t('Webform submissions'),
    'description' => t('Tokens related to webform submission.'),
    'needs-data' => 'webform_submission',
  ];
  $webform_submission['webform_authorizenet_total_amount'] = [
    'name' => t('Authorize.net total amount'),
    'description' => t('The total amount for authorize.net calculated based on handler and submission data.'),
  ];

  return [
    'types' => $types,
    'tokens' => [
      'webform_submission' => $webform_submission,
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function webform_authorizenet_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'webform_submission' && !empty($data['webform_submission'])) {
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $data['webform_submission'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'webform_authorizenet_total_amount':
          $handler_settings = SubmissionHelper::getHandlerConfiguration($webform_submission);
          $configuration = $handler_settings['settings'];
          $configuration = SubmissionHelper::getConfigurationWithSubmissionContext($configuration, $webform_submission);

          $total_amount = $configuration['number_of_items'] * $configuration['item_price'];
          $replacements[$original] = $total_amount;
          break;
      }
    }
  }

  return $replacements;
}
