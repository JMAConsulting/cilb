<?php
use CRM_Mjwshared_ExtensionUtil as E;

// This enables custom fields for FinancialTrxn entities
return [
  [
    'name' => 'cg_extend_objects:FinancialTrxn',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'cg_extend_objects',
        'label' => E::ts('Financial Transaction (Payment)'),
        'value' => 'FinancialTrxn',
        'name' => 'civicrm_financial_trxn',
        'is_reserved' => TRUE,
        'is_active' => TRUE,
      ],
    ],
    'match' => ['option_group_id', 'name'],
  ],
  [
    'name' => 'CustomGroup_Payment_details',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Payment_details',
        'title' => E::ts('Payment details'),
        'extends' => 'FinancialTrxn',
        'style' => 'Inline',
        'help_pre' => '',
        'help_post' => '',
        'weight' => 8,
        'collapse_adv_display' => TRUE,
        'icon' => '',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
