<?php
use CRM_CilbExamRegistration_ExtensionUtil as E;

return [
  [
    'name' => 'Job_UpdatePaperBasedExams',
    'entity' => 'Job',
    'cleanup' => 'never',
    'update' => 'never',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Update Paper-Based Exams',
        'description' => E::ts('Generates Candidate Number for paper-based exams that don\'t have one assigned yet'),
        'api_entity' => 'Job',
        'api_action' => 'updatePaperBasedExams',
        'run_frequency' => 'Hourly',
        'parameters' => 'version=4
runInNonProductionEnvironment=1',
        'is_active' => FALSE,
      ],
    ],
  ],
];
