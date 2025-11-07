<?php

use CRM_CilbReports_ExtensionUtil as E;

return [
  'cilb_reports_mwfreport_last_run_date' => [
    'name' => 'cilb_reports_mwfreport_last_run_date',
    'title' => E::ts('Last Cron Run date of the Monday, Wednesday, Friday report'),
    'is_domain' => 1,
    'is_contact' => 0,
    'type' => 'String',
    'html_type' => 'text',
  ],
];