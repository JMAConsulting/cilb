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
        'api_action' => 'processPaymentprocessorWebhooks',
        // CRM_Core_JobManager dispatches via civicrm_api(), which requires 'version'
        // in the params - checkPermissions=0 matches the old APIv3 job function's
        // behaviour of running unauthenticated (the site-key auth in
        // CRM_Core_JobManager::execute() is the actual trust boundary for cron).
        'parameters' => "version=4\ncheckPermissions=0\ndeleteOld=-3 month",
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
