<?php
use CRM_AdvancedEvents_ExtensionUtil as E;
return [
  [
    'name' => 'Navigation_afsearchManageEventTemplates',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Manage Event Templates'),
        'name' => 'afsearchManageEventTemplates',
        'url' => 'civicrm/admin/eventTemplate',
        'icon' => 'crm-i fa-list-alt',
        'permission' => [
          'view own event templates',
          'view all event templates',
        ],
        'permission_operator' => 'AND',
        'parent_id.name' => 'Events',
        'weight' => 1,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
];
