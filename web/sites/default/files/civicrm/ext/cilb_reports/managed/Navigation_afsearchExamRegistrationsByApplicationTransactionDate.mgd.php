<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  [
    'name' => 'Navigation_afsearchExamRegistrationsByApplicationTransactionDate',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Exam Registrations by Application Transaction Date'),
        'name' => 'afsearchExamRegistrationsByApplicationTransactionDate',
        'url' => 'civicrm/report/exam-registration-transactions',
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
