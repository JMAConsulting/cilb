<?php

use CRM_CilbChargebackLinks_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_Participant_Webform',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Participant_Webform',
        'title' => E::ts('Participant Webform'),
        'extends' => 'Participant',
        'style' => 'Inline',
        'help_pre' => '',
        'help_post' => '',
        'collapse_adv_display' => TRUE,
        'icon' => '',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Participant_Webform_CustomField_Webform_Machine_Name',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Participant_Webform',
        'name' => 'Url',
        'label' => E::ts('Url'),
        'html_type' => 'Text',
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];