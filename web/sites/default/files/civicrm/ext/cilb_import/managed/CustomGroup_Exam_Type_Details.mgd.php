<?php
use CRM_Cilb_Import_ExtensionUtil as E;
return [
  [
    'name' => 'CustomGroup_Exam_Type_Details',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Exam_Type_Details',
        'title' => E::ts('CILB Exam Category Details'),
        'extends' => 'OptionValue',
        'extends_entity_column_value:name' => ['event_type'],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_imported_id',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'imported_id',
        'label' => E::ts('Imported Category ID'),
        'html_type' => 'Text',
        'column_name' => 'imported_category_id_1',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_Speciality_ID',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'Speciality_ID',
        'label' => E::ts('Specialty ID'),
        'html_type' => 'Text',
        'column_name' => 'specialty_id_2',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_DBPR_Code',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'DBPR_Code',
        'label' => E::ts('DBPR Code'),
        'html_type' => 'Text',
        'column_name' => 'dbpr_code_3',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_category_abbrev',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'category_abbrev',
        'label' => E::ts('Category Abbreviation'),
        'html_type' => 'Text',
        'column_name' => 'category_abbreviation_4',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_CILB_Class',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'CILB_Class',
        'label' => E::ts('CILB Class'),
        'html_type' => 'Text',
        'column_name' => 'cilb_class_5',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_Gus_Code',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'Gus_Code',
        'label' => E::ts('Gus Code'),
        'html_type' => 'Text',
        'column_name' => 'gus_code_6',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_Gus_DBPR_Code',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'Gus_DBPR_Code',
        'label' => E::ts('Gus DBPR Code'),
        'html_type' => 'Text',
        'column_name' => 'gus_dbpr_code_7',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Exam_Type_Details_CustomField_Category_Name_Spanish',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Exam_Type_Details',
        'name' => 'Category_Name_Spanish',
        'label' => E::ts('Spanish Label'),
        'html_type' => 'Text',
        'column_name' => 'spanish_label_8',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
