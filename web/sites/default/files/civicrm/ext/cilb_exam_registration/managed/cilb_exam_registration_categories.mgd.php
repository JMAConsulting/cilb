<?php

use CRM_CilbExamRegistration_ExtensionUtil as E;

return [
  [
    'name' => 'OptionValue_Event_Type_Business_and_Finance',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'event_type',
        'label' => E::ts('Business and Finance'),
        'value' => '78',
        'name' => 'Business and Finance',
        'weight' => 78,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionValue_Event_Type_Pool_Spa_Servicing_Business_and_Finance',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'event_type',
        'label' => E::ts('Pool & Spa Servicing Business and Finance'),
        'value' => '79',
        'name' => 'Pool & Spa Servicing Business and Finance',
        'weight' => 79,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];