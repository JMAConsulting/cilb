<?php
use CRM_Cilb_Import_ExtensionUtil as E;
return [
  [
    'name' => 'CustomGroup_Candidate_Result',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Candidate_Result',
        'title' => E::ts('Candidate Result'),
        'extends' => 'Participant',
        'style' => 'Inline',
        'help_pre' => E::ts(''),
        'help_post' => E::ts(''),
        'weight' => 5,
        'collapse_adv_display' => TRUE,
        'icon' => '',
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_Candidate_Result_CustomField_Candidate_Score',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Candidate_Result',
        'name' => 'Candidate_Score',
        'label' => E::ts('Score'),
        'data_type' => 'Float',
        'html_type' => 'Text',
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'score_33',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Candidate_Result_CustomField_Bypass_Reregistration_Check',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Candidate_Result',
        'name' => 'Bypass_Reregistration_Check',
        'label' => E::ts('Bypass Reregistration Check'),
        'description' => E::ts('Normally if a candidate has registered for or taken an exam part, they are prevented from registering again. Set this field to Yes to bypass this check and allow the candidate to re-register.'),
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Candidate_Result_CustomField_Date_Exam_Taken',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Candidate_Result',
        'name' => 'Date_Exam_Taken',
        'label' => E::ts('Date Exam Taken'),
        'data_type' => 'Date',
        'html_type' => 'Select Date',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
