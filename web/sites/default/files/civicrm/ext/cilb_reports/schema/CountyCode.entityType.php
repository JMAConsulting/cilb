<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  'name' => 'CountyCode',
  'table' => 'civicrm_county_code',
  'class' => 'CRM_CilbReports_DAO_CountyCode',
  'getInfo' => fn() => [
    'title' => E::ts('CountyCode'),
    'title_plural' => E::ts('CountyCodes'),
    'description' => E::ts('FIXME'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique CountyCode ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'county' => [
      'title' => ts('County'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'description' => ts('Name of County'),
      'usage' => [
        'import',
        'export',
      ],
    ],
    'county_code' => [
      'title' => E::ts('County Code'),
      'sql_type' => 'varchar(4)',
      'input_type' => 'Number',
      'description' => E::ts('County Code'),
    ],
  ],
  'getIndices' => fn() => [],
  'getPaths' => fn() => [],
];
