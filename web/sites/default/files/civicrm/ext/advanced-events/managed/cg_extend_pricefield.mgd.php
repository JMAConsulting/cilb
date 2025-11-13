<?php

return [
  [
    'name' => 'cg_extend_objects:PriceField',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'cg_extend_objects',
        'label' => ts('PriceField'),
        'value' => 'PriceField',
        'name' => 'civicrm_price_field',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => ['option_group_id', 'name'],
    ],
  ],
];