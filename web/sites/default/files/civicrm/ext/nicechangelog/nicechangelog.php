<?php

require_once 'nicechangelog.civix.php';

use CRM_Nicechangelog_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function nicechangelog_civicrm_config(&$config): void {
  _nicechangelog_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 */
function nicechangelog_civicrm_install(): void {
  _nicechangelog_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function nicechangelog_civicrm_enable(): void {
  _nicechangelog_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * Fallback route registration (the menu-xml mixin normally handles this).
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function nicechangelog_civicrm_xmlMenu(&$files): void {
  foreach (glob(__DIR__ . '/xml/Menu/*.xml') as $file) {
    if (!in_array($file, $files, TRUE)) {
      $files[] = $file;
    }
  }
}

/**
 * Implements hook_civicrm_tabset().
 *
 * Repoint the core "Change Log" tab on a contact to our enhanced view, which
 * shows the field-level changes inline and adds action/component filters.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_tabset
 */
function nicechangelog_civicrm_tabset($tabsetName, &$tabs, $context): void {
  if ($tabsetName !== 'civicrm/contact/view') {
    return;
  }
  if (!CRM_Core_BAO_Log::useLoggingReport()) {
    return;
  }
  $contactId = $context['contact_id'] ?? NULL;
  if (!$contactId) {
    return;
  }
  foreach ($tabs as &$tab) {
    if (($tab['id'] ?? NULL) === 'log') {
      $tab['url'] = CRM_Utils_System::url('civicrm/nicechangelog/changelog', "reset=1&cid={$contactId}");
    }
  }
}
