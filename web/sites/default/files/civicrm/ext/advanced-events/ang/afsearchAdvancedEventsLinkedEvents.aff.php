<?php
use CRM_AdvancedEvents_ExtensionUtil as E;
return [
  'type' => 'search',
  'title' => E::ts('Advanced Events Linked Events'),
  'icon' => 'fa-list-alt',
  'permission' => [
    'edit all events',
  ],
  'search_displays' => [
    'AdvancedEvents_Linked_Events.Linked_Events',
  ],
];
