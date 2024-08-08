<?php

require_once 'delete_inactive_users.civix.php';
// phpcs:disable
use CRM_DeleteInactiveUsers_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function delete_inactive_users_civicrm_config(&$config): void {
  _delete_inactive_users_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function delete_inactive_users_civicrm_install(): void {
  _delete_inactive_users_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function delete_inactive_users_civicrm_enable(): void {
  _delete_inactive_users_civix_civicrm_enable();
}

// function delete_inactive_users_civicrm_api(&$params) {
//   if ($params['entity'] === 'Job' && $params['action'] === 'delete_inactive_users') {
//     return array(
//       'api' => array(
//         'entity' => 'Job',
//         'action' => 'delete_inactive_users',
//         'description' => 'Deletes inactive user accounts',
//         'params' => array(),
//         'returns' => array('success' => 1),
//       ),
//     );
//   }
//   return array();
// }
