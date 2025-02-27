<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Exam Registrations by Application Transaction Date'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/report/exam-registration-transactions',
  'permission' => [
    'access CiviReport',
  ],
];
