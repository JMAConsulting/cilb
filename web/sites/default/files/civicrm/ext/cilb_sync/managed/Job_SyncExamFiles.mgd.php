<?php
use CRM_CILB_Sync_ExtensionUtil as E;

return [
  [
    'name' => 'Job_SyncExameFiles',
    'entity' => 'Job',
    'cleanup' => 'never',
    'update' => 'never',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Sync Entity ID File',
        'description' => E::ts('Daily import of Candidate entity ID data.'),
        'api_entity' => 'Job',
        'api_action' => 'syncExamFiles',
        'run_frequency' => 'Daily',
        'parameters' => 'runInNonProductionEnvironment=1
version=4
dateToSync=yesterday',
        'is_active' => TRUE,
      ],
    ],
  ],
];
