<?php
use CRM_Cilb_Import_ExtensionUtil as E;
return [
  [
    'name' => 'CustomGroup_Exam_Details',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Exam_Details',
        'title' => E::ts('CILB Exam Details'),
        'extends' => 'Event',
        'weight' => 2,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Details_CustomField_imported_id',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Details',
        'name' => 'imported_id',
        'label' => E::ts('Imported ID (pti_category_exam_parts.PK_Exam_Part_ID)'),
        'html_type' => 'Text',
        'column_name' => 'imported_id_pti_category_exam_pa_9',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Details_CustomField_Exam_Part',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Details',
        'name' => 'Exam_Part',
        'label' => E::ts('Exam Part'),
        'html_type' => 'Text',
        'column_name' => 'exam_part_10',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Details_CustomField_Exam_Part_Sequence',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Details',
        'name' => 'Exam_Part_Sequence',
        'label' => E::ts('Sequence'),
        'html_type' => 'Text',
        'column_name' => 'sequence_11',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Details_CustomField_Exam_Series_Code',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Details',
        'name' => 'Exam_Series_Code',
        'label' => E::ts('Series Code'),
        'html_type' => 'Text',
        'column_name' => 'series_code_12',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Details_CustomField_Exam_Question_Count',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Details',
        'name' => 'Exam_Question_Count',
        'label' => E::ts('Question Count'),
        'html_type' => 'Text',
        'column_name' => 'question_count_13',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
