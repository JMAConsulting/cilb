<?php
use CRM_CILB_Sync_ExtensionUtil as E;

return [
  'name' => 'PaperExamImportMap',
  'table' => 'civicrm_paper_exam_import_map',
  'class' => 'CRM_CILB_Sync_DAO_PaperExamImportMap',
  'getInfo' => fn() => [
    'title' => E::ts('PaperExamImportMap'),
    'title_plural' => E::ts('PaperExamImportMaps'),
    'description' => E::ts('FIXME'),
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
    'contact_id' => [
      'title' => E::ts('Contact ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Contact'),
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
  ],
  'getIndices' => fn() => [],
  'getPaths' => fn() => [],
];
