<?php

use CRM_AuthNetEcheck_ExtensionUtil as E;

return [
  [
    'name' => 'Authorize.Net (Credit Card)',
    'entity' => 'PaymentProcessorType',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'AuthorizeNetCreditcard',
        'title' => E::ts('Authorize.net (Credit Card)'),
        'user_name_label' => 'API Login ID',
        'password_label' => 'Transaction Key',
        'signature_label' => 'Signature Key',
        'class_name' => 'Payment_AuthNetCreditcard',
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
