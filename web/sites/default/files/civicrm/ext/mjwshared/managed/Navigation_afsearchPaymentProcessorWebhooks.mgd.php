<?php
use CRM_Mjwshared_ExtensionUtil as E;

return [
  [
    'name' => 'Navigation_afsearchPaymentProcessorWebhooks',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Payment Processor Webhooks'),
        'name' => 'afsearchPaymentProcessorWebhooks',
        'url' => 'civicrm/paymentprocessorwebhooks',
        'icon' => 'crm-i fa-list-alt',
        'permission' => [
          'edit contributions',
        ],
        'permission_operator' => 'AND',
        'parent_id.name' => 'CiviContribute',
        'weight' => 19,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
];
