<?php

require_once 'advimport.civix.php';
use CRM_Advimport_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function advimport_civicrm_config(&$config) {
  if (isset(Civi::$statics[__FUNCTION__])) { return; }
  Civi::$statics[__FUNCTION__] = 1;

  Civi::dispatcher()->addListener('dataexplorer.boot', ['\Civi\Advimport\Events', 'fireDataExplorerBoot']);

  _advimport_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function advimport_civicrm_install() {
  _advimport_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function advimport_civicrm_enable() {
  _advimport_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_alterLogTables().
 *
 * Exclude advimport from logging tables since they hold mostly temp data.
 */
function advimport_civicrm_alterLogTables(&$logTableSpec) {
  $len = strlen('civicrm_advimport_');

  foreach ($logTableSpec as $key => $val) {
    if (substr($key, 0, $len) == 'civicrm_advimport_') {
      unset($logTableSpec[$key]);
    }
  }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
 */
function advimport_civicrm_navigationMenu(&$menu) {
  $item[] =  array (
    'label' => E::ts('Advanced Import', array('domain' => 'advimport')),
    'name'       => 'Advanced Import',
    'url'        => 'civicrm/advimport?reset=1',
    'permission' => 'administer CiviCRM',
    'operator'   => NULL,
    'separator'  => NULL,
  );
  _advimport_civix_insert_navigation_menu($menu, 'Administer', $item[0]);

  _advimport_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_advimport_helpers()
 */
function advimport_civicrm_advimport_helpers(&$helpers) {
  $helpers[] = [
    'class' => 'CRM_Advimport_Advimport_APIv3',
    'label' => E::ts('APIv3 Entities'),
  ];
  $helpers[] = [
    'class' => 'CRM_Advimport_Advimport_MembershipsAndContributions',
    'label' => E::ts('Memberships and Contributions'),
  ];
  $helpers[] = [
    'class' => 'CRM_Advimport_Advimport_Phone2Action',
    'label' => E::ts('Contact + Activity'),
  ];
  $helpers[] = [
    'class' => 'CRM_Advimport_Advimport_GroupContactAdd',
    'label' => E::ts('Groups - Add Contact to Group'),
  ];
  $helpers[] = [
    'class' => 'CRM_Advimport_Advimport_GroupContactRemove',
    'label' => E::ts('Groups - Remove Contact from Group'),
  ];
  $helpers[] = [
    'class' => 'CRM_Advimport_Advimport_LookupTableContact',
    'label' => E::ts('Lookup Table to Batch Update Contacts'),
  ];

  if (function_exists('stripe_civicrm_config')) {
    $helpers[] = [
      'class' => 'CRM_Advimport_Advimport_StripeSubscriptions',
      'label' => E::ts('Stripe Subscriptions - experimental'),
    ];
    $helpers[] = [
      'class' => 'CRM_Advimport_Advimport_StripeFailedEvents',
      'label' => E::ts('Stripe Failed Events - experimental'),
    ];
  }
}

/**
 * Implements hook_civicrm_alterReportVar().
 */
function advimport_civicrm_alterReportVar($type, &$vars, $form) {
  switch ($type) {
  case 'outputhandlers':
    // $vars['\Civi\Report\Civiexportexcel\Excel2007'] = '\Civi\Report\Civiexportexcel\Excel2007';
    break;
  case 'actions':
    $tasks = CRM_Report_BAO_ReportInstance::getActionMetadata();
    $helpers = CRM_Advimport_BAO_Advimport::getHelpers();

    foreach ($helpers as $h) {
      if (empty($h['type']) || $h['type'] != 'report-batch-update') {
        continue;
      }

      $vars['report_instance.advimport.' . $h['class']] = ['title' => $h['label']];
    }

    break;
  }
}

/**
 * Implements hook_civicrm_searchTasks().
 */
function advimport_civicrm_searchTasks($objectType, &$tasks) {
  $path = CRM_Utils_System::currentPath();

  if ($objectType == 'contact' /* && $path == 'civicrm/contact/search/custom' */) {
    $helpers = CRM_Advimport_BAO_Advimport::getHelpers();

    foreach ($helpers as $h) {
      if (empty($h['type']) || $h['type'] != 'search-batch-update') {
        continue;
      }

      $tasks[] = [
        'title' => $h['label'],
        'class' => $h['class'],
        'result' => FALSE,
      ];
    }
  }
}

/**
 * Implements hook_civicrm_alterAPIPermissions().
 *
 * NB: api3 entities only. The entity calls will assume: only users with "import contacts"
 * can create/update an import, and otherwise non-admins can view only their own imports.
 */
function advimport_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  $permissions['advimport']['geterrors'] = ['import contacts'];
  // We are deprecating api3 calls, so for security reasons, advimport.{get,create} requires 'administer CiviCRM'.
  $permissions['advimport_row']['create'] = ['access CiviCRM'];
  // CRM/Core/DAO/permissions.php will sometimes implicitly set the action to 'update'
  $permissions['advimport_row']['update'] = ['access CiviCRM'];
  $permissions['advimport_row']['bulkupdate'] = ['access CiviCRM'];
  $permissions['advimport_row']['field'] = ['access CiviCRM'];
  $permissions['advimport_row']['import'] = ['access CiviCRM'];
}
