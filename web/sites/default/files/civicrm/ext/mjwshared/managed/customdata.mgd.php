<?php
use CRM_Mjwshared_ExtensionUtil as E;

// This enables custom fields for FinancialTrxn entities
return [
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
        'is_active' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
