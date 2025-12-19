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

use CRM_Mjwshared_ExtensionUtil as E;

return [
  'mjwshared_refundpaymentui' => [
    'name' => 'mjwshared_refundpaymentui',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => TRUE,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Enable refund payment via UI?'),
    'description' => E::ts('Enables a "Refund payment" option next to the edit payment option on Payments. Find payments by expanding contributions.
    For more detail see the <a href="%1">Refund documentation</a>', [1 => 'https://docs.civicrm.org/mjwshared/en/latest/refunds/']),
    'html_attributes' => [],
    'settings_pages' => [
      'mjwshared' => [
        'weight' => 21,
      ]
    ],
  ],
  'mjwshared_disablerecordrefund' => [
    'name' => 'mjwshared_disablerecordrefund',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => TRUE,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Disable the "Record Refund" link on edit contribution'),
    'description' => E::ts('By default CiviCRM includes a "Record Refund" link on edit contribution. This can be confusing when our payment refund UI is enabled because the contribution "Record Refund" does not communicate with the payment processor.'),
    'html_attributes' => [],
    'settings_pages' => [
      'mjwshared' => [
        'weight' => 22,
      ]
    ],
  ],
  'mjwshared_jsdebug' => [
    'name' => 'mjwshared_jsdebug',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => 0,
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Enable Javascript debugging?'),
    'description' => E::ts('Enables debug logging to browser console for javascript based payment processors.'),
    'html_attributes' => [],
    'settings_pages' => [
      'mjwshared' => [
        'weight' => 99,
      ]
    ],
  ],
];
