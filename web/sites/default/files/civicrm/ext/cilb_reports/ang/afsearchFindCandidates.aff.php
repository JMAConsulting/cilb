<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Find Candidates'),
  'description' => E::ts('Custom Find Candidates form using SearchKit'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/event/search',
];
