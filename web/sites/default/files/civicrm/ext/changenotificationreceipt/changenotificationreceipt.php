<?php

require_once 'changenotificationreceipt.civix.php';

use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

function changenotificationreceipt_civicrm_config(&$config): void {
  _changenotificationreceipt_civix_civicrm_config($config);
}

function changenotificationreceipt_civicrm_install(): void {
  _changenotificationreceipt_civix_civicrm_install();
  _changenotificationreceipt_install_schema();
}

function changenotificationreceipt_civicrm_enable(): void {
  _changenotificationreceipt_civix_civicrm_enable();
  _changenotificationreceipt_install_schema();
}

function changenotificationreceipt_civicrm_uninstall(): void {
  CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS `civicrm_change_notification_queue`', [], TRUE, NULL, FALSE, FALSE);
}

function _changenotificationreceipt_install_schema(): void {
  if (CRM_Core_DAO::checkTableExists('civicrm_change_notification_queue')) {
    return;
  }
  $defn = include __DIR__ . '/schema/ChangeNotificationQueue.entityType.php';
  CRM_Core_DAO::executeQuery(Civi::schemaHelper()->arrayToSql($defn), [], TRUE, NULL, FALSE, FALSE);
}

function changenotificationreceipt_civicrm_pre(string $op, string $objectName, $id, array &$params): void {
  CRM_ChangeNotificationReceipt_Watcher::pre($op, $objectName, $id, $params);
}

function changenotificationreceipt_civicrm_post(string $op, string $objectName, $objectId, &$objectRef): void {
  CRM_ChangeNotificationReceipt_Watcher::post($op, $objectName, $objectId, $objectRef);
}

function changenotificationreceipt_civicrm_custom(string $op, $groupID, $entityID, &$params): void {
  CRM_ChangeNotificationReceipt_Watcher::custom($op, $groupID, $entityID, $params);
}
