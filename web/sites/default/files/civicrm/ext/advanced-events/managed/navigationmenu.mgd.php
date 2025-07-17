<?php

use CRM_AdvancedEvents_ExtensionUtil as E;

return [
  [
    'name' => 'advanced_events_settings',
    'entity' => 'Navigation',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Advanced Events Settings'),
        'name' => 'advanced_events_settings',
        'url' => 'civicrm/admin/setting/advanced_events',
        'permission' => 'administer Advanced Events',
        'permission_operator' => 'OR',
        'parent_id.name' => 'CiviEvent',
        'is_active' => TRUE,
        'has_separator' => 0,
        'weight' => 90,
      ],
      'match' => ['name'],
    ],
  ],
];
