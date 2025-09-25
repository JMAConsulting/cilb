<?php

use CRM_CILB_Sync_ExtensionUtil as E;

return [
  'sftp_cilb_url' => [
    'name' => 'sftp_cilb_url',
    'type' => 'String',
    'title' => E::ts('URL'),
    'description' => E::ts('sFTP URL'),
    'html_type' => 'text',
    'default' => '',
  ],
  'sftp_cilb_url_port' => [
    'name' => 'sftp_cilb_url_port',
    'type' => 'Int',
    'title' => E::ts('Port'),
    'description' => E::ts('sFTP URL Port'),
    'html_type' => 'text',
    'default' => '22',
  ],
  'sftp_cilb_user' => [
    'name' => 'sftp_cilb_user',
    'type' => 'String',
    'title' => E::ts('sFTP User'),
    'description' => E::ts('User for authentication via sFTP'),
    'html_type' => 'text',
    'default' => '',
  ],
  'sftp_cilb_password' => [
    'name' => 'sftp_cilb_password',
    'type' => 'String',
    'title' => E::ts('sFTP User Password'),
    'description' => E::ts('Password for authentication via sFTP'),
    'html_type' => 'password',
    'default' => '',
  ],
  'sftp_cilb_home_dir' => [
    'name' => 'sftp_cilb_home_dir',
    'type' => 'String',
    'title' => E::ts('Files Directory'),
    'description' => E::ts('Default path to look for files'),
    'html_type' => 'text',
    'default' => '',
  ],
];

