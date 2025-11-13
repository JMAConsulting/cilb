<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use CRM_AdvancedEvents_ExtensionUtil as E;

return [
  'advanced_events_function_location' => [
    'name' => 'advanced_events_function_location',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Manage Events Tabs: Enable Location'),
    'html_attributes' => [],
    'settings_pages' => ['advanced_events' => ['weight' => 10]],
  ],

  'advanced_events_function_fee' => [
    'name' => 'advanced_events_function_fee',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Manage Events Tabs: Enable Fees'),
    'html_attributes' => [],
    'settings_pages' => ['advanced_events' => ['weight' => 20]],
  ],

  'advanced_events_function_registration' => [
    'name' => 'advanced_events_function_registration',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Manage Events Tabs: Enable Online Registration'),
    'html_attributes' => [],
    'settings_pages' => ['advanced_events' => ['weight' => 30]],
  ],

  'advanced_events_function_reminder' => [
    'name' => 'advanced_events_function_reminder',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Manage Events Tabs: Enable Reminders'),
    'html_attributes' => [],
    'settings_pages' => ['advanced_events' => ['weight' => 40]],
  ],

  'advanced_events_function_friend' => [
    'name' => 'advanced_events_function_friend',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Manage Events Tabs: Enable Tell A Friend'),
    'html_attributes' => [],
    'settings_pages' => ['advanced_events' => ['weight' => 50]],
  ],

  'advanced_events_function_pcp' => [
    'name' => 'advanced_events_function_pcp',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Manage Events Tabs: Enable Personal Campaigns'),
    'html_attributes' => [],
    'settings_pages' => ['advanced_events' => ['weight' => 60]],
  ],

  'advanced_events_function_repeat' => [
    'name' => 'advanced_events_function_repeat',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 1,
    'add' => '5.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Manage Events Tabs: Enable Repeating Event'),
    'html_attributes' => [],
    'settings_pages' => ['advanced_events' => ['weight' => 70]],
  ],
];
