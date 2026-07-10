<?php

use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

return [
  'name' => 'ChangeNotificationQueue',
  'table' => 'civicrm_change_notification_queue',
  'class' => 'CRM_ChangeNotificationReceipt_DAO_ChangeNotificationQueue',
  'getInfo' => fn() => [
    'title' => E::ts('Change Notification Queue'),
    'title_plural' => E::ts('Change Notification Queue'),
    'description' => E::ts('Detected changes to CILB contact, exam registration and contribution records awaiting a nightly notification email.'),
    'log' => FALSE,
  ],
  'getIndices' => fn() => [
    'index_contact_status' => [
      'fields' => [
        'contact_id' => TRUE,
        'status' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('Queue Entry ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique queue entry ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'contact_id' => [
      'title' => E::ts('Contact ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('Contact whose record changed (recipient of the notification).'),
      'entity_reference' => [
        'entity' => 'Contact',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'entity_table' => [
      'title' => E::ts('Entity Table'),
      'sql_type' => 'varchar(64)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Table of the changed record, e.g. civicrm_contact.'),
    ],
    'entity_id' => [
      'title' => E::ts('Entity ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('Primary key of the changed record.'),
    ],
    'action' => [
      'title' => E::ts('Action'),
      'sql_type' => 'varchar(16)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('create, edit or delete.'),
    ],
    'changes' => [
      'title' => E::ts('Changes'),
      'sql_type' => 'longtext',
      'input_type' => 'TextArea',
      'description' => E::ts('List of {label, old, new} describing what changed.'),
      'serialize' => CRM_Core_DAO::SERIALIZE_JSON,
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'sql_type' => 'timestamp',
      'input_type' => NULL,
      'required' => TRUE,
      'description' => E::ts('When the change was detected.'),
      'default' => 'CURRENT_TIMESTAMP',
    ],
    'status' => [
      'title' => E::ts('Status'),
      'sql_type' => 'varchar(16)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('pending, sent, skipped or error.'),
      'default' => 'pending',
    ],
    'processed_date' => [
      'title' => E::ts('Processed Date'),
      'sql_type' => 'timestamp',
      'input_type' => NULL,
      'description' => E::ts('When the nightly job processed this row.'),
    ],
  ],
];
