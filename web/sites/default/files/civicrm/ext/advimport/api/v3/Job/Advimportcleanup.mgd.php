<?php

/**
 * @file
 * This file declares a managed database record of type "Job".
 */

return [
  0 => [
    'name' => 'Advimportcleanup Job',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Advimportcleanup Job',
      'description' => 'Automatic cleanup of old Advimport imports.',
      'run_frequency' => 'Daily',
      'api_entity' => 'Job',
      'api_action' => 'advimportcleanup',
      'parameters' => "days=30",
      'is_active' => 0,
    ],
    'update' => 'never',
  ],
];
