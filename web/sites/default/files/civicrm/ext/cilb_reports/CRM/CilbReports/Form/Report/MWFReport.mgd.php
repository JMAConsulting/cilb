<?php

// This file declares a managed database record of type "ReportTemplate".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'CRM_CilbReports_Form_Report_MWFReport',
    'entity' => 'ReportTemplate',
    'params' => [
      'version' => 3,
      'label' => 'MWFReport',
      'description' => 'MWFReport (cilb_reports)',
      'class_name' => 'CRM_CilbReports_Form_Report_MWFReport',
      'report_url' => 'cilb_reports/mwfreport',
      'component' => 'CiviEvent',
    ],
  ],
];
