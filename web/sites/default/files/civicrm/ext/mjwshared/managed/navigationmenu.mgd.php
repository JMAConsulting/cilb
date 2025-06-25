<?php

use CRM_Mjwshared_ExtensionUtil as E;

return [
  [
    'name' => 'mjwshared_settings',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Payment Shared Settings'),
        'name' => 'mjwshared_settings',
        'url' => 'civicrm/admin/setting/mjwshared',
        'permission' => 'administer Payment Shared',
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviContribute',
        'is_active' => TRUE,
        'has_separator' => 0,
        'weight' => 90,
      ],
      'match' => ['name'],
    ],
  ],
];
