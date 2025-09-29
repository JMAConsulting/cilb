<?php

use CRM_CILB_Sync_ExtensionUtil as E;

return [
  'sftp_pearson_url' => [
    'name' => 'sftp_pearson_url',
    'type' => 'String',
    'title' => E::ts('URL'),
    'description' => E::ts('sFTP URL'),
    'html_type' => 'text',
    'default' => '',
    'settings_pages' => [
      'sftp' => ['weight' => 10],
    ],
  ],
  'sftp_pearson_url_port' => [
    'name' => 'sftp_pearson_url_port',
    'type' => 'Int',
    'title' => E::ts('Port'),
    'description' => E::ts('sFTP URL Port'),
    'html_type' => 'text',
    'default' => '22',
    'settings_pages' => [
      'sftp' => ['weight' => 20],
    ],
  ],
  'sftp_pearson_user' => [
    'name' => 'sftp_pearson_user',
    'type' => 'String',
    'title' => E::ts('sFTP User'),
    'description' => E::ts('User for authentication via sFTP'),
    'html_type' => 'text',
    'default' => '',
    'settings_pages' => [
      'sftp' => ['weight' => 30],
    ],
  ],
  'sftp_pearson_password' => [
    'name' => 'sftp_pearson_password',
    'type' => 'String',
    'title' => E::ts('sFTP User Password'),
    'description' => E::ts('Password for authentication via sFTP'),
    'html_type' => 'password',
    'default' => '',
    'settings_pages' => [
      'sftp' => ['weight' => 40],
    ],
  ],
  'sftp_pearson_home_dir' => [
    'name' => 'sftp_pearson_home_dir',
    'type' => 'String',
    'title' => E::ts('Files Directory'),
    'description' => E::ts('Default path to look for files'),
    'html_type' => 'text',
    'default' => '',
    'settings_pages' => [
      'sftp' => ['weight' => 50],
    ],
  ],
];

