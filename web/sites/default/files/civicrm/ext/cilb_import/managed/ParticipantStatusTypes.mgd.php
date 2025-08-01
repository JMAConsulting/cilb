<?php
use CRM_Cilb_Import_ExtensionUtil as E;

return [
  [
    'name' => 'ParticipantStatusType_Pass',
    'entity' => 'ParticipantStatusType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Pass',
        'label' => E::ts('Pass'),
        'class' => 'Positive',
        'is_reserved' => TRUE,
        'is_counted' => TRUE,
        'weight' => 18,
      ],
    ],
  ],
  [
    'name' => 'ParticipantStatusType_Fail',
    'entity' => 'ParticipantStatusType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Fail',
        'label' => E::ts('Fail'),
        'class' => 'Positive',
        'is_reserved' => TRUE,
        'is_counted' => TRUE,
        'weight' => 17,
      ],
    ],
  ],
];
