<?php
use CRM_Mjwshared_ExtensionUtil as E;
return [
  'name' => 'PaymentprocessorWebhook',
  'table' => 'civicrm_paymentprocessor_webhook',
  'class' => 'CRM_Mjwshared_DAO_PaymentprocessorWebhook',
  'getInfo' => fn() => [
    'title' => E::ts('Paymentprocessor Webhook'),
    'title_plural' => E::ts('Paymentprocessor Webhooks'),
    'description' => E::ts('Track the processing of payment processor webhooks'),
    'log' => TRUE,
  ],
  'getIndices' => fn() => [
    'index_event_id' => [
      'fields' => [
        'event_id' => TRUE,
      ],
    ],
    'index_created_date' => [
      'fields' => [
        'created_date' => TRUE,
      ],
    ],
    'index_processed_date' => [
      'fields' => [
        'processed_date' => TRUE,
      ],
    ],
    'index_status_processed_date' => [
      'fields' => [
        'status' => TRUE,
        'processed_date' => TRUE,
      ],
    ],
    'index_identifier' => [
      'fields' => [
        'identifier' => TRUE,
      ],
    ],
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique PaymentprocessorWebhook ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'payment_processor_id' => [
      'title' => E::ts('Payment Processor'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Select',
      'description' => E::ts('Payment Processor for this webhook'),
      'pseudoconstant' => [
        'table' => 'civicrm_payment_processor',
        'key_column' => 'id',
        'label_column' => 'name',
      ],
      'entity_reference' => [
        'entity' => 'PaymentProcessor',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'event_id' => [
      'title' => E::ts('Event ID'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Webhook event ID'),
    ],
    'trigger' => [
      'title' => E::ts('Trigger'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Webhook trigger event type'),
    ],
    'created_date' => [
      'title' => E::ts('Created Date'),
      'sql_type' => 'timestamp',
      'input_type' => 'Date',
      'readonly' => TRUE,
      'description' => E::ts('When the webhook was first received by the IPN code'),
      'default' => 'CURRENT_TIMESTAMP',
    ],
    'processed_date' => [
      'title' => E::ts('Processed Date'),
      'sql_type' => 'timestamp',
      'input_type' => 'Date',
      'readonly' => TRUE,
      'description' => E::ts('Has this webhook been processed yet?'),
      'default' => NULL,
    ],
    'status' => [
      'title' => E::ts('Status'),
      'sql_type' => 'varchar(32)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Processing status'),
      'default' => 'new',
    ],
    'identifier' => [
      'title' => E::ts('Identifier'),
      'sql_type' => 'varchar(255)',
      'input_type' => 'Text',
      'description' => E::ts('Optional key to group webhooks, as needed by some processors.'),
    ],
    'message' => [
      'title' => E::ts('Message'),
      'sql_type' => 'varchar(1024)',
      'input_type' => 'Text',
      'description' => E::ts('Stores data sent that is needed for processing. JSON suggested.'),
      'default' => '',
    ],
    'data' => [
      'title' => E::ts('Data'),
      'sql_type' => 'text',
      'input_type' => 'TextArea',
      'description' => E::ts('Stores data sent that is needed for processing. JSON suggested.'),
    ],
  ],
];
