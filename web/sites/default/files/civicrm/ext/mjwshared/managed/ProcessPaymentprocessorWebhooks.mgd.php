<?php

use CRM_Mjwshared_ExtensionUtil as E;

return [
  [
    'name' => 'ProcessPaymentProcessorWebhooks',
    'entity' => 'Job',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Process PaymentProcessor Webhooks',
        'description' => E::ts('Process incomplete payment processor webhooks'),
        'run_frequency' => 'Always',
        'api_entity' => 'Job',
        'api_action' => 'process_paymentprocessor_webhooks',
        'parameters' => 'delete_old=-3 month',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
