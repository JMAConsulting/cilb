<?php

require_once 'cilb_import.civix.php';

use CRM_Cilb_Import_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cilb_import_civicrm_config(&$config): void {
  _cilb_import_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cilb_import_civicrm_install(): void {
  _cilb_import_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cilb_import_civicrm_enable(): void {
  _cilb_import_civix_civicrm_enable();
}
