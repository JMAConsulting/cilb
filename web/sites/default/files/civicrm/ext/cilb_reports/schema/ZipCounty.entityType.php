<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  'name' => 'ZipCounty',
  'table' => 'civicrm_zip_county',
  'class' => 'CRM_CilbReports_DAO_ZipCounty',
  'getInfo' => fn() => [
    'title' => E::ts('ZipCounty'),
    'title_plural' => E::ts('ZipCounties'),
    'description' => E::ts('FIXME'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique ZipCounty ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'zip_code' => [
      'title' => ts('Zip Code'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'description' => ts('Store both US (zip5) AND international postal codes. App is responsible for country/region appropriate validation.'),
      'add' => '1.1',
      'usage' => [
        'import',
        'export',
        'duplicate_matching',
      ],
      'input_attrs' => [
        'size' => '6',
      ],
    ],
    'county_id' => [
      'title' => E::ts('County ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to County Code'),
      'entity_reference' => [
        'entity' => 'CountyCode',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
  ],
  'getIndices' => fn() => [
    'UI_zip_county' => [
      'fields' => [
        'zip_code' => TRUE,
        'county_id' => TRUE,
      ],
      'unique' => TRUE,
    ],
  ],
  'getPaths' => fn() => [],
];
