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
  'search_displays' => [
    'Candidate_Reconciliation_Report.Candidate_Reconciliation_Report',
  ],
];
