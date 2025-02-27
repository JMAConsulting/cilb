<?php
use CRM_CilbReports_ExtensionUtil as E;
return [
  'type' => 'search',
  'title' => E::ts('Plumbing Exam Registrations by Registration Transaction Date'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/report/plumbing-exam-registration-transactions',
  'permission' => [
    'access CiviReport',
  ],
  'search_displays' => [
    'Plumbing_Exam_Registrations_by_Registration_Transaction_Date.Plumbing_Exam_Registrations_by_Registration_Transaction_Date',
  ],
];
