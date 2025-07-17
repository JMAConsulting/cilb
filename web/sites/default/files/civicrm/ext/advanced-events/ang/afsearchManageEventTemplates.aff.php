<?php
use CRM_AdvancedEvents_ExtensionUtil as E;
return [
  'type' => 'search',
  'title' => E::ts('Manage Event Templates'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/admin/eventTemplate',
  'permission' => [
    'view own event templates',
    'view all event templates',
  ],
  'search_displays' => [
    'Event_Templates.Event_Templates_Table_1',
  ],
];
