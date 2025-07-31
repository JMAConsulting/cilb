<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Candidates For Payment'),
  'description' => E::ts('Used for showing candidate records linked to a payment. Payment ID filter is provided by context.'),
  'icon' => 'fa-list-alt',
  'permission' => [
    'access CiviContribute',
  ],
];
