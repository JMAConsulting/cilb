<?php

require_once 'uplang.civix.php';
use CRM_Uplang_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function uplang_civicrm_config(&$config) {
  _uplang_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_buildForm().
 */
function uplang_civicrm_buildForm($formName, &$form) {
  // Administer / Localization / Languages, Currency, Locations
  if ($formName == 'CRM_Admin_Form_Setting_Localization') {
    CRM_Uplang_Admin_Form_Setting_Localization::buildForm($form);
  }
}

/**
 * Implements hook_civicrm_pageRun().
 */
function uplang_civicrm_pageRun(&$page) {
  // Administer / System Settings / Manage Extensions
  if (is_a($page, 'CRM_Admin_Page_Extensions')) {
    CRM_Uplang_Admin_Form_Setting_Localization::addRefreshButton('page-header', $page);
  }
}
