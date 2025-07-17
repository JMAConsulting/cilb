<?php
use CRM_AdvancedEvents_ExtensionUtil as E;
return [
  'name' => 'EventTemplate',
  'table' => 'civicrm_event_template',
  'class' => 'CRM_AdvancedEvents_DAO_EventTemplate',
  'getInfo' => fn() => [
    'title' => E::ts('Event Template'),
    'title_plural' => E::ts('Event Templates'),
    'description' => E::ts('CiviCRM Event Template map table'),
    'log' => TRUE,
    'add' => '5.0',
    'label_field' => 'title',
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique EventTemplate ID'),
      'add' => '5.0',
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'event_id' => [
      'title' => E::ts('Event ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to Event'),
      'add' => '5.0',
      'entity_reference' => [
        'entity' => 'Event',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'template_id' => [
      'title' => E::ts('Template ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Select',
      'description' => E::ts('FK to Event'),
      'add' => '5.0',
      'pseudoconstant' => [
        'callback' => 'CRM_AdvancedEvents_BAO_EventTemplate::getEventTemplatesPseudoConstant',
      ],
      'entity_reference' => [
        'entity' => 'Event',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'title' => [
      'title' => E::ts('Event Template Title'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'localizable' => TRUE,
      'description' => E::ts('Event Template Title'),
      'add' => '5.0',
      'usage' => [
        'import',
        'export',
        'duplicate_matching',
      ],
    ],
  ],
];
