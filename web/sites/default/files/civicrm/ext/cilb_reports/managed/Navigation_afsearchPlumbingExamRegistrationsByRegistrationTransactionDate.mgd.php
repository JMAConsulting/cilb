<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  [
    'name' => 'Navigation_afsearchPlumbingExamRegistrationsByRegistrationTransactionDate',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Plumbing Exam Registrations by Registration Transaction Date'),
        'name' => 'afsearchPlumbingExamRegistrationsByRegistrationTransactionDate',
        'url' => 'civicrm/report/plumbing-exam-registration-transactions',
        'icon' => 'crm-i fa-list-alt',
        'permission' => [
          'access CiviReport',
        ],
        'permission_operator' => 'AND',
        'parent_id.name' => 'Reports',
        'weight' => 1,
      ],
      'match' => ['name', 'domain_id'],
    ],
  ],
];
