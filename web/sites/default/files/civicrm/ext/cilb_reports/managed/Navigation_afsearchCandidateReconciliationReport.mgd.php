<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  [
    'name' => 'Navigation_afsearchCandidateReconciliationReport',
    'entity' => 'Navigation',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'label' => E::ts('Candidate Reconciliation Report'),
        'name' => 'afsearchCandidateReconciliationReport',
        'url' => 'civicrm/report/candidate-reconciliation-report',
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
