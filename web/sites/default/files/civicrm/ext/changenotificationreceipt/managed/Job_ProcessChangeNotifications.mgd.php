<?php

use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

return [
  [
    'name' => 'Job_ProcessChangeNotifications',
    'entity' => 'Job',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Process CILB Change Notifications',
        'description' => E::ts('Sends a daily email with an updated PDF receipt to each contact whose CILB contact, exam registration or contribution records changed.'),
        'api_entity' => 'Job',
        'api_action' => 'processChangeNotifications',
        'run_frequency' => 'Daily',
        'parameters' => 'version=4',
        'is_active' => TRUE,
      ],
    ],
  ],
];
