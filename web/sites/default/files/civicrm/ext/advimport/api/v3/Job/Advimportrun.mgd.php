<?php

/**
 * @file
 * This file declares a managed database record of type "Job".
 */

return [
  0 => [
    'name' => 'Advimportrun',
    'entity' => 'Job',
    'params' => [
      'version' => 3,
      'name' => 'Run a specific Advimport job',
      'description' => 'Run a specific Advimport job',
      'run_frequency' => 'Weekly',
      'api_entity' => 'Job',
      'api_action' => 'advimportrun',
      'parameters' => "helper=CRM_Example_Advimport_Myimport\nnotify=me@example.org\ngroup_or_tag=group",
      'is_active' => 0,
    ],
    'update' => 'never',
  ],
];
