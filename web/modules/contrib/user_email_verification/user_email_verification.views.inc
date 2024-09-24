<?php

/**
 * @file
 * User email verification views hooks.
 */

/**
 * Implements hook_views_data().
 */
function user_email_verification_views_data() {
  $data = [];

  $data['user_email_verification']['table']['group'] = t('User Email verification');
  $data['user_email_verification']['table']['base'] = [
    'field' => 'uid',
    'title' => t('User Email verification'),
    'help' => t('Details about verification of users Email.'),
  ];
  $data['user_email_verification']['table']['join'] = [
    'users_field_data' => [
      'left_field' => 'uid',
      'field' => 'uid',
    ],
  ];

  $data['user_email_verification']['uid'] = [
    'title' => t('UID'),
    'help' => t('ID of the related user.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'relationship' => [
      'title' => t('User'),
      'help' => t('ID of the related user.'),
      'base' => 'users_field_data',
      'base field' => 'uid',
      'id' => 'standard',
    ],
  ];

  $data['user_email_verification']['verified'] = [
    'title' => t('Verified'),
    'help' => t('Is email verified flag.'),
    'field' => [
      'id' => 'boolean',
    ],
    'filter' => [
      'id' => 'user_email_verification_verified',
      'label' => t('Is email verified.'),
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['user_email_verification']['last_reminder'] = [
    'title' => t('Last reminder'),
    'help' => t('Last notification timestamp.'),
    'field' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'argument' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',
    ],
  ];

  $data['user_email_verification']['reminders'] = [
    'title' => t('Reminders'),
    'help' => t('Number of reminders sent.'),
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['user_email_verification']['state'] = [
    'title' => t('Verification state'),
    'help' => t('Verification state: 0 - in progress, 1 - approved, 2 - blocked, 3 - deleted, 4 - on hold.'),
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  return $data;
}
