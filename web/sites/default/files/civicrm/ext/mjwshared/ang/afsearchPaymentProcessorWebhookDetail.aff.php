<?php
use CRM_Mjwshared_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Payment Processor Webhook Detail'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/paymentprocessorwebhooks/detail',
  'permission' => [
    'edit contributions',
  ],
];
