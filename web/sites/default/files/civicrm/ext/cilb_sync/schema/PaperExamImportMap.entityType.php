<?php
use CRM_CILB_Sync_ExtensionUtil as E;

return [
  'name' => 'PaperExamImportMap',
  'table' => 'civicrm_paper_exam_import_map',
  'class' => 'CRM_CILB_Sync_DAO_PaperExamImportMap',
  'getInfo' => fn() => [
    'title' => E::ts('PaperExamImportMap'),
    'title_plural' => E::ts('PaperExamImportMaps'),
    'description' => E::ts('Exam to Advanced Import Mapping'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique PaperExamImportMap ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'exam_id' => [
      'title' => E::ts('Exam ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Event'),
      'entity_reference' => [
        'entity' => 'Event',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
      'required' => TRUE,
    ],
    'advanced_import_id' => [
      'title' => E::ts('Advanced Import ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Adv Import'),
      'entity_reference' => [
        'entity' => 'Advimport',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
      'required' => TRUE,
    ],
  ],
  'getIndices' => fn() => [],
  'getPaths' => fn() => [],
];
