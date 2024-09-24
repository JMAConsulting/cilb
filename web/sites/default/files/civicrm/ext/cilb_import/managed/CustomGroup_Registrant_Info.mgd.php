<?php
use CRM_Cilb_Import_ExtensionUtil as E;
return [
  [
    'name' => 'CustomGroup_Registrant_Info',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Registrant_Info',
        'title' => E::ts('Registrant Info'),
        'weight' => 3,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_Registrant_Info_CustomField_SSN',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Registrant_Info',
        'name' => 'SSN',
        'label' => E::ts('Social Security Number'),
        'html_type' => 'Text',
        'column_name' => 'social_security_number_14',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Registrant_Info_CustomField_Restriction_Reason',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Registrant_Info',
        'name' => 'Restriction_Reason',
        'label' => E::ts('Restriction Reason'),
        'html_type' => 'Text',
        'column_name' => 'restriction_reason_15',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Registrant_Info_CustomField_Is_Restricted',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Registrant_Info',
        'name' => 'Is_Restricted',
        'label' => E::ts('Is Restricted?'),
        'data_type' => 'Boolean',
        'html_type' => 'CheckBox',
        'column_name' => 'is_restricted__16',
        'serialize' => 1,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
