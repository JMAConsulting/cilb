<?php

return [
  [
    'name' => 'cg_extend_objects:PriceFieldValue',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'cg_extend_objects',
        'label' => ts('PriceFieldValue'),
        'value' => 'PriceFieldValue',
        'name' => 'civicrm_price_field_value',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
      'match' => ['option_group_id', 'name'],
    ],
  ],
];