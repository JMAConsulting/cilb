<?php

use CRM_AuthNetEcheck_ExtensionUtil as E;

return [
  [
    'name' => 'Authorize.Net (Accept.js)',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'AuthNetAcceptjs',
        'title' => E::ts('Authorize.net (Accept.js)'),
        'user_name_label' => 'API Login ID',
        'password_label' => 'Transaction Key',
        'signature_label' => 'Public Client Key',
        'class_name' => 'Payment_AuthNetAcceptjs',
        'url_site_default' => 'https://unused.org',
        'billing_mode' => 1,
        'is_recur' => TRUE,
        'payment_instrument_id:name' => 'Credit Card',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
