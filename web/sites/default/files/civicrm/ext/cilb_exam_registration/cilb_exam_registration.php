<?php

require_once 'cilb_exam_registration.civix.php';

use CRM_CilbExamRegistration_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cilb_exam_registration_civicrm_config(&$config): void {
  _cilb_exam_registration_civix_civicrm_config($config);
  // This hook sometimes runs twice
  if (isset(Civi::$statics[__FUNCTION__])) {
    return;
  }
  Civi::$statics[__FUNCTION__] = 1;
  Civi::dispatcher()->addListener('hook_civicrm_pre', '_cilb_exam_registration_external_identifier_set');
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cilb_exam_registration_civicrm_install(): void {
  _cilb_exam_registration_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cilb_exam_registration_civicrm_enable(): void {
  _cilb_exam_registration_civix_civicrm_enable();
}

function _cilb_exam_registration_external_identifier_set(\Civi\Core\Event\PreEvent $event) {
  if ($event->action == 'create' && \Civi\Api4\Utils\CoreUtil::isContact($event->entity)) {
    $params = $event->params;
    if (empty($params['external_identifier'])) {
      $current_max_external_identifier = CRM_Core_DAO::singleValueQuery("SELECT MAX(external_identifier) FROM civicrm_contact");
      $current_max_external_identifier = (float) $current_max_external_identifier;
      $params['external_identifier'] = $current_max_external_identifier + 1;
      $uniquenessCheck = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contact WHERE external_identifier = %1", [
        1 => [$params['external_identifier'], 'String'],
      ]);
      while (!empty($uniquenessCheck)) {
        $params['external_identifier'] = $params['external_identifier'] + 1;
        $uniquenessCheck = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contact WHERE external_identifier = %1", [
          1 => [$params['external_identifier'], 'String'],
        ]);
      }
    }
    $event->params = $params;
  }
}
