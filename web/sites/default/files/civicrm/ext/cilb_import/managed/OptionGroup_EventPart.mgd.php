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
];
