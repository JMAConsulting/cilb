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

use CRM_Sweetalert_ExtensionUtil as E;

return [
  'sweetalert_override_mode' => [
    'name' => 'sweetalert_override_mode',
    'type' => 'String',
    'html_type' => 'select',
    'default' => 'frontend',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Use Sweetalert for regular CiviCRM alerts'),
    'options' => [
      'nowhere' => E::ts('Disabled'),
      'frontend' => E::ts('Override alerts on the frontend'),
      'everywhere' => E::ts('Override alerts everywhere'),
    ],
    'settings_pages' => [
      'display' => ['section' => 'theme', 'weight' => 1000],
    ],
  ],
];
