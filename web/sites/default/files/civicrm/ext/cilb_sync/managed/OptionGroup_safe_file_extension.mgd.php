<?php
use CRM_CILB_Sync_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_safe_file_extension',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'safe_file_extension',
        'title' => E::ts('Safe File Extension'),
        'option_value_fields' => ['name', 'label', 'description'],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'OptionGroup_safe_file_extension_OptionValue_dat',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'safe_file_extension',
        'label' => E::ts('dat'),
        'value' => '20',
        'name' => 'dat',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_safe_file_extension_OptionValue_zip',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'safe_file_extension',
        'label' => E::ts('zip'),
        'value' => '21',
        'name' => 'zip',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
];
