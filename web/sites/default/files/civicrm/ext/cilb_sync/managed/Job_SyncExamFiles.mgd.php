<?php
use CRM_CILB_Sync_ExtensionUtil as E;

return [
  [
    'name' => 'Job_SyncExameFiles',
    'entity' => 'Job',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'last_run' => '',
        'last_run_end' => '',
        'name' => 'Sync Exam Files',
        'description' => E::ts('Daily import of Candidate entity and score data.'),
        'api_entity' => 'Job',
        'api_action' => 'syncExamFiles',
        'run_frequency' => 'Daily',
        'parameters' => 'runInNonProductionEnvironment=1
version=4',
        'is_active' => FALSE,
      ],
    ],
  ],
];
