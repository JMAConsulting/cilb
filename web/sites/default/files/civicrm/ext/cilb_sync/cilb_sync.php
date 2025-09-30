<?php

require_once 'cilb_sync.civix.php';

use CRM_CILB_Sync_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cilb_sync_civicrm_config(&$config): void {
  _cilb_sync_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cilb_sync_civicrm_install(): void {
  _cilb_sync_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cilb_sync_civicrm_enable(): void {
  _cilb_sync_civix_civicrm_enable();
}


function cilb_sync_civicrm_navigationMenu(&$menu) {
  _cilb_sync_civix_insert_navigation_menu($menu, 'Administer/System Settings', [
    'label' => E::ts('sFTP Settings'),
    'name' => 'sftp_settings',
    'url' => 'civicrm/admin/setting/sftp?reset=1',
    'permission' => 'administer CiviCRM'
  ]);
}

/**
 * Custom Import Wrapper for migrating score data
 * Implements hook_civicrm_advimport_helpers()
 */
function cilb_sync_civicrm_advimport_helpers(&$helpers) {
  $helpers[] = [
    'class' => 'CRM_CILB_Sync_AdvImport_PearsonVueWrapper',
    'label' => E::ts('PearsonVue Scores Import'),
  ];
  $helpers[] = [
    'class' => 'CRM_CILB_Sync_AdvImport_CILBEntityWrapper',
    'label' => E::ts('CILB Entities Import'),
  ];
  $helpers[] = [
    'class' => 'CRM_CILB_Sync_AdvImport_PaperExamWrapper',
    'label' => E::ts('Paper Exam Scores Import'),
  ];
}

