<?php

use CRM_CilbExamRegistration_ExtensionUtil as E;

return [
  [
    'name' => 'CustomField_Exam_Category_this_exam_applies_to',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Details',
        'name' => 'Exam_Category_this_exam_applies_to',
        'label' => E::ts('Exam Category this exam applies to'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'option_group_id.name' => 'event_type',
        'serialize' => 1,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomField_Exam_Category',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Candidate_Result',
        'name' => 'Candidate_Exam_Category',
        'label' => E::ts('B&F Exam Category'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'option_group_id.name' => 'event_type',
        'serialize' => 1,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
