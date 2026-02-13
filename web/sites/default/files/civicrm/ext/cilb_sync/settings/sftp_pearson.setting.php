<?php

use CRM_CILB_Sync_ExtensionUtil as E;

return [
  'sftp_pearson_url' => [
    'name' => 'sftp_pearson_url',
    'type' => 'String',
    'title' => E::ts('URL'),
    'description' => E::ts('sFTP Pearson URL'),
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
    'description' => E::ts('sFTP Pearson URL Port'),
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
    'description' => E::ts('Pearson User for authentication via sFTP'),
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
    'description' => E::ts('Pearson Password for authentication via sFTP'),
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
    'description' => E::ts('Pearson Default path to look for files'),
    'html_type' => 'text',
    'default' => '',
    'settings_pages' => [
      'sftp' => ['weight' => 50],
    ],
  ],
  'sftp_pearson_zip_date_file_name_format' => [
    'name' => 'sftp_pearson_date_file_name_format',
    'type' => 'String',
    'title' => E::ts('Date Format in Zip File name'),
    'description' => E::ts('Format of the date string in file name format e.g. Ymd. Needs to be written in the PHP Date Format string'),
    'html_type' => 'text',
    'default' => 'Ymd',
    'settings_pages' => [
      'sftp' => ['weight' => 50],
    ],
  ],
  'sftp_pearson_dat_date_file_name_format' => [
    'name' => 'sftp_pearson_date_file_name_format',
    'type' => 'String',
    'title' => E::ts('Date Format in Dat File name'),
    'description' => E::ts('Format of the date string in file name format e.g. Ymd. Needs to be written in the PHP Date Format string'),
    'html_type' => 'text',
    'default' => 'Y-m-d',
    'settings_pages' => [
      'sftp' => ['weight' => 60],
    ],
  ],
];

