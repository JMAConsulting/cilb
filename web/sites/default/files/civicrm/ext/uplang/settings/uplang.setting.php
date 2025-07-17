<?php

use CRM_Uplang_ExtensionUtil as E;

return [
  'uplang_ignore_ext' => [
    'name' => 'uplang_ignore_ext',
    'type' => 'String',
    'default' => '',
    'html_type' => 'text',
    'add' => '1.0',
    'title' => E::ts('Ignore Extensions'),
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => E::ts('If you have custom translations, they can be excluded from the update process. Enter the short machine name (ex: uplang, not the old-style FQDN). You can enter multiple extensions by separating them with a comma.'),
    'settings_pages' => [
      'uplang' => [
        'weight' => 15,
      ],
    ],
  ],
];
