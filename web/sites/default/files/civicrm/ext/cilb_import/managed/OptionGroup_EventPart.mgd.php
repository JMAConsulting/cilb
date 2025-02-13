<?php

use CRM_Cilb_Import_ExtensionUtil as E;

return [
    [
      'name' => 'OptionGroup_Exam_Part',
      'entity' => 'OptionGroup',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'Exam_Part',
          'title' => E::ts('Exam Part'),
          'data_type' => 'String',
          'is_reserved' => FALSE,
          'option_value_fields' => [
            'name',
            'label',
            'description',
          ],
        ],
        'match' => [
          'name',
        ],
      ],
    ],
    [
      'name' => 'OptionGroup_Exam_Part_OptionValue_Contract_Administration',
      'entity' => 'OptionValue',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'option_group_id.name' => 'Exam_Part',
          'label' => E::ts('Contract Administration'),
          'value' => 'CA',
          'name' => 'CA',
        ],
        'match' => [
          'option_group_id',
          'name',
          'value',
        ],
      ],
    ],
    [
      'name' => 'OptionGroup_Exam_Part_OptionValue_Project_Management',
      'entity' => 'OptionValue',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'option_group_id.name' => 'Exam_Part',
          'label' => E::ts('Project Management'),
          'value' => 'PM',
          'name' => 'PM',
        ],
        'match' => [
          'option_group_id',
          'name',
          'value',
        ],
      ],
    ],
    [
      'name' => 'OptionGroup_Exam_Part_OptionValue_Trade_Knowledge',
      'entity' => 'OptionValue',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'option_group_id.name' => 'Exam_Part',
          'label' => E::ts('Trade Knowledge'),
          'value' => 'TK',
          'name' => 'TK',
        ],
        'match' => [
          'option_group_id',
          'name',
          'value',
        ],
      ],
    ],
    [
      'name' => 'OptionGroup_Exam_Part_OptionValue_Business_Finance',
      'entity' => 'OptionValue',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'option_group_id.name' => 'Exam_Part',
          'label' => E::ts('Business and Finance'),
          'value' => 'BF',
          'name' => 'BF',
        ],
        'match' => [
          'option_group_id',
          'name',
          'value',
        ],
      ],
    ],
    // custom fields extending this option group - keep here so they are always created after
    [
      'name' => 'CustomGroup_Exam_Part_Options',
      'entity' => 'CustomGroup',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'name' => 'Exam_Part_Options',
          'title' => E::ts('Exam Part Options'),
          'extends' => 'OptionValue',
          'extends_entity_column_value:name' => ['Exam_Part'],
          'style' => 'Inline',
          'help_pre' => '',
          'help_post' => '',
          'weight' => 8,
          'collapse_adv_display' => TRUE,
          'icon' => '',
        ],
        'match' => [
          'name',
          'extends',
        ],
      ],
    ],
    [
      'name' => 'CustomGroup_Exam_Part_Options_CustomField_Category_Specific',
      'entity' => 'CustomField',
      'cleanup' => 'unused',
      'update' => 'unmodified',
      'params' => [
        'version' => 4,
        'values' => [
          'custom_group_id.name' => 'Exam_Part_Options',
          'name' => 'Category_Specific',
          'label' => E::ts('Category Specific?'),
          'data_type' => 'Boolean',
          'html_type' => 'Radio',
          'default_value' => '0',
          'text_length' => 255,
          'note_columns' => 60,
          'note_rows' => 4,
          'column_name' => 'category_specific_34',
        ],
        'match' => [
          'name',
          'extends',
        ],
      ],
    ],
];
