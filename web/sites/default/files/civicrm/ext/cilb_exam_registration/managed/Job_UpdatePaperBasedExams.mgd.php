<?php
use CRM_CILB_Sync_ExtensionUtil as E;

return [
  [
    'name' => 'Job_UpdatePaperBasedExams',
    'entity' => 'Job',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'last_run' => '',
        'last_run_end' => '',
        'name' => 'Update Paper-Based Exams',
        'description' => E::ts('Generates Candidate Number for paper-based exams that don\'t have one assigned yet'),
        'api_entity' => 'Job',
        'api_action' => 'updatePaperBasedExams',
        'run_frequency' => 'Daily',
        'parameters' => 'runInNonProductionEnvironment=1
version=4',
        'is_active' => FALSE,
      ],
    ],
  ],
];
