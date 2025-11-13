<?php
use CRM_Mjwshared_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Payment Processor Webhooks'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/paymentprocessorwebhooks',
  'permission' => [
    'edit contributions',
  ],
];
