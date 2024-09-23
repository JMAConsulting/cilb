<?php
use CRM_Cilb_Import_ExtensionUtil as E;
return [
  [
    'name' => 'CustomGroup_cilb_candidate_entity',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'cilb_candidate_entity',
        'title' => E::ts('CILB Candidate Entities'),
        'weight' => 4,
        'is_multiple' => TRUE,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_cilb_candidate_entity_CustomField_entity_id',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'cilb_candidate_entity',
        'name' => 'entity_id',
        'label' => E::ts('Entity ID'),
        'html_type' => 'Text',
        'column_name' => 'entity_id_17',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_cilb_candidate_entity_CustomField_class_code',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'cilb_candidate_entity',
        'name' => 'class_code',
        'label' => E::ts('Class Code'),
        'html_type' => 'Text',
        'column_name' => 'class_code_18',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_cilb_candidate_entity_CustomField_exam_category',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'cilb_candidate_entity',
        'name' => 'exam_category',
        'label' => E::ts('Exam Category'),
        'html_type' => 'Autocomplete-Select',
        'column_name' => 'exam_category_19',
        'option_group_id.name' => 'event_type',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
