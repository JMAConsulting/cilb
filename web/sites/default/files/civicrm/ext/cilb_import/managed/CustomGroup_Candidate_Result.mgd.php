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
        'extends' => 'Participant',
        'weight' => 5,
        'collapse_adv_display' => TRUE,
        'title' => E::ts('Candidate Result'),
      ],
      'match' => [
        'name',
      ],
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
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'column_name' => 'bypass_reregistration_check_36',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Candidate_Result_CustomField_Candidate_Number',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Candidate_Result',
        'name' => 'Candidate_Number',
        'label' => E::ts('Candidate Number'),
        'html_type' => 'Text',
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'candidate_number_76',
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
        'date_format' => 'mm/dd/yy',
        'column_name' => 'date_exam_taken_80',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Registrant_Info_Exam_Language_Preference',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Registrant_Info_Exam_Language_Preference',
        'title' => E::ts('Registrant Info :: Exam Language Preference'),
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
    'name' => 'OptionGroup_Registrant_Info_Exam_Language_Preference_OptionValue_English',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'Registrant_Info_Exam_Language_Preference',
        'label' => E::ts('English'),
        'value' => '1',
        'name' => 'English',
        'description' => '',
        'Exam_Part_Options.Category_Specific' => NULL,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Registrant_Info_Exam_Language_Preference_OptionValue_Spanish',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'Registrant_Info_Exam_Language_Preference',
        'label' => E::ts('Español'),
        'value' => '2',
        'name' => 'Spanish',
        'description' => '',
        'Exam_Part_Options.Category_Specific' => NULL,
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Candidate_Result_CustomField_Exam_Language_Preference',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Candidate_Result',
        'name' => 'Exam_Language_Preference',
        'label' => E::ts('Exam Language Preference'),
        'html_type' => 'Select',
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'exam_language_preference_89',
        'option_group_id.name' => 'Registrant_Info_Exam_Language_Preference',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Candidate_Result_CustomField_ADA_Accommodations_Needed',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Candidate_Result',
        'name' => 'ADA_Accommodations_Needed',
        'label' => E::ts('ADA Accommodations Needed'),
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'ada_accommodations_needed_90',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];