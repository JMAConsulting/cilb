<?php

// NOTE: this isn't strictly related to the import
// but are fields used in the webform - just adding here for ease

use CRM_Cilb_Import_ExtensionUtil as E;

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
        'title' => E::ts('Candidate Webform'),
        'extends' => 'Participant',
        'style' => 'Inline',
        'help_pre' => '',
        'help_post' => '',
        'weight' => 6,
        'collapse_adv_display' => TRUE,
        'icon' => '',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  // TODO: clarify if/how this field is used
  [
    'name' => 'CustomGroup_Participant_Webform_CustomField_Url',
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
        'is_active' => FALSE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Participant_Webform_CustomField_Candidate_Representative_Name',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Participant_Webform',
        'name' => 'Candidate_Representative_Name',
        'label' => E::ts('Candidate Representative Name'),
        'html_type' => 'Text',
        'text_length' => 255,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Participant_Webform_CustomField_Candidate_Payment',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Participant_Webform',
        'name' => 'Candidate_Payment',
        'label' => E::ts('Candidate Payment'),
        'data_type' => 'EntityReference',
        'html_type' => 'Autocomplete-Select',
        'fk_entity' => 'Contribution',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];