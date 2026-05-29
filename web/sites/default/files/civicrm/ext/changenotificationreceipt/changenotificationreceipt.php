<?php

require_once 'changenotificationreceipt.civix.php';

use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 */
function changenotificationreceipt_civicrm_config(&$config): void {
  _changenotificationreceipt_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 */
function changenotificationreceipt_civicrm_install(): void {
  _changenotificationreceipt_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function changenotificationreceipt_civicrm_enable(): void {
  _changenotificationreceipt_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_pre().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pre
 */
function changenotificationreceipt_civicrm_pre(string $op, string $objectName, $id, array &$params): void {
  CRM_ChangeNotificationReceipt_Watcher::pre($op, $objectName, $id, $params);
}

/**
 * Implements hook_civicrm_post().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_post
 */
function changenotificationreceipt_civicrm_post(string $op, string $objectName, $objectId, &$objectRef): void {
  CRM_ChangeNotificationReceipt_Watcher::post($op, $objectName, $objectId, $objectRef);
}

/**
 * Implements hook_civicrm_custom().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_custom
 */
function changenotificationreceipt_civicrm_custom(string $op, $groupID, $entityID, &$params): void {
  CRM_ChangeNotificationReceipt_Watcher::custom($op, $groupID, $entityID, $params);
}
