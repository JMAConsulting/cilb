<?php

require_once 'cilb_exam_registration.civix.php';

use Civi\Api4\CustomField;
use Civi\Api4\Event;
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
  Civi::dispatcher()->addListener('hook_civicrm_pre', '_cilb_exam_registration_candidate_number_population');
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

function _cilb_exam_registration_candidate_number_population(\Civi\Core\Event\PreEvent $event) {
  if ($event->action == 'create' && $event->entity == 'Participant') {
    $customFieldDetails = CustomField::get(FALSE)
      ->addSelect('*', 'custom_group_id.table_name', 'custom_group_id')
      ->addWhere('name', '=', 'Candidate_Number')
      ->addWhere('custom_group_id:name', '=', 'Candidate_Result')
      ->execute()->first();
    $customFieldID = $customFieldDetails['id'];
    $eventDetails = Event::get(FALSE)
      ->addSelect('*', 'custom.*')
      ->addWhere('id', '=', $event->params['event_id'])
      ->execute()->first();
    $params = $event->params;
    // Only process if we are dealing with a paper exam
    if ($eventDetails['Exam_Details.Exam_Format'] == 'paper') {
      $candidate_number_in_params = FALSE;
      if (array_key_exists('custom', $params) && !$candidate_number_in_params) {
        foreach ($params as $customFieldID => $param) {
          foreach ($param as $index => $customValue) {
            if ($customValue['custom_field_id'] == $customFieldID) {
              $candidate_number_in_params = TRUE;
            }
          }
        }
      }
      elseif (array_key_exists('custom_' . $customFieldID, $params) && !$candidate_number_in_params) {
        $candidate_number_in_params = TRUE;
      }
      // The Custom Field doesn't seem to be in the custom array
      if (!$candidate_number_in_params) {
        // Find the current maximum id if there is one
        $maxID = CRM_Core_DAO::singleValueQuery("SELECT COALESCE(max(COALESCE(cv.{$customFieldDetails['column_name']}, 0)),0)
          FROM {$customFieldDetails['custom_group_id.table_name']} AS cv
          INNER JOIN civicrm_participant p ON p.id = cv.entity_id
          WHERE p.event_id = %1
        ", [
          1 => [$params['event_id'], 'Positive'],
        ]);
        $maxID = (float) $maxID;
        if ($maxID === 0) {
          // Default value is 570102 as per ticket #86dxtfr64
          $candidate_id = '570102';
        }
        else {
          $candidate_id = $maxID + 1;
        }
        // Store it in both formats in the params.
        $params['custom_' . $customFieldID] = $candidate_id;
        $params['custom'][$customFieldID][-1] = [
          'id' => NULL,
          'value' => $candidate_id,
          'type' => $customFieldDetails['data_type'],
          'custom_field_id' => $customFieldID,
          'custom_group_id' => $customFieldDetails['custom_group_id'],
          'table_name' => $customFieldDetails['custom_group_id.table_name'],
          'column_name' => $customFieldDetails['column_name'],
          'file_id' => NULL,
          'is_multiple' => $customFieldDetails['is_multiple'],
          'serialize' => $customFieldDetails['serialize'],
        ];
      }
    }
    $event->params = $params;
  }
  if ($event->action == 'create' && \Civi\Api4\Utils\CoreUtil::isContact($event->entity)) {
    $params = $event->params;
    if (empty($params['external_identifier'])) {
      $current_max_external_identifier = CRM_Core_DAO::singleValueQuery("SELECT MAX(external_identifier) FROM civicrm_contact");
      $current_max_external_identifier = (float) $current_max_external_identifier;
      $params['external_identifier'] = $current_max_external_identifier + 1;
      $uniquenessCheck = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contact WHERE external_identifier = %1", [
        1 => [$params['external_identifier']]
      ]);
      while (!empty($uniquenessCheck)) {
        $params['external_identifier'] = $params['external_identifier'] + 1;
        $uniquenessCheck = CRM_Core_DAO::singleValueQuery("SELECT count(id) FROM civicrm_contact WHERE external_identifier = %1", [
          1 => [$params['external_identifier']]
        ]);
      }
    }
    $event->params = $params;
  }
}
