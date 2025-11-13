<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Candidate Reconciliation Report'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/report/candidate-reconciliation-report',
  'permission' => [
    'access CiviReport',
  ],
];
