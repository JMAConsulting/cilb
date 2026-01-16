<?php

require_once 'cilb_exam_registration.civix.php';

use CRM_CilbExamRegistration_ExtensionUtil as E;
use Drupal\user\Entity\User;

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

function cilb_exam_registration_civicrm_buildForm($formName, &$form) {
  if ($formName === 'CRM_Admin_Form_ScheduleReminders') {
    // Add Participant custom field as trigger date option
    $registrationExpiryField = \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('name', 'label', 'custom_group_id.extends_entity_column_id')
      ->addWhere('name', '=', 'Registration_Expiry_Date')
      ->addWhere('custom_group_id.name', '=', 'Candidate_Result')
      ->execute()->first();
    
    if ($registrationExpiryField) {
      $startActionDateElement = $form->getElement('start_action_date');
      if ($startActionDateElement) {
        // Insert as new option after standard dates
        $currentOptions = $startActionDateElement->_options;
        $newOptions = [];
        
        foreach ($currentOptions as $option) {
          $newOptions[$option['attr']['value']] = $option['text'];
          
          if ($option['attr']['value'] === 'registration_end_date') {
            $newOptions[$registrationExpiryField['name']] = $registrationExpiryField['label'] . ' (Participant)';
          }
        }
        
        $startActionDateElement->setOptions($newOptions);
      }
    }
  }
}

/**
 * Implements hook_civicrm_validateForm().
 */
function cilb_exam_registration_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName === 'CRM_Admin_Form_ScheduleReminders' && $fields['mapping_id'] === 2) {
    if ($fields['start_action_date'] === 'Candidate_Result.Registration_Expiry_Date') {
      $customField = \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('custom_group_id.extends_entity_column_value')
        ->addWhere('name', '=', 'Registration_Expiry_Date')
        ->addWhere('custom_group_id.name', '=', 'Candidate_Result')
        ->execute()->first();
      
      if ($customField) {
        // Get event types that extend Candidate_Result custom group
        $allowedEventTypes = $customField['custom_group_id.extends_entity_column_value'] ?? [];
        
        // Selected event types from the form
        $selectedEventTypes = array_map('trim', explode(',', $fields['entity_value']));
        
        // Find event types that DON'T support this custom field
        $invalidEventTypes = array_diff($selectedEventTypes, $allowedEventTypes);
        
        if (!empty($invalidEventTypes)) {
          // Get labels for better error message
          $invalidLabels = \Civi\Api4\Event::get(FALSE)
            ->addSelect('event_type_id:label')
            ->addClause('OR', [['id', 'IN', $invalidEventTypes]])
            ->execute()
            ->indexBy('id')
            ->column('event_type_id:label');
          
          $allowedLabels = \Civi\Api4\Event::get(FALSE)
            ->addSelect('event_type_id:label')
            ->addClause('OR', [['id', 'IN', $allowedEventTypes]])
            ->execute()
            ->indexBy('id')
            ->column('event_type_id:label');
          
          $errors['entity_value'] = E::ts('Selected event types %1 do not support Registration Expiry Date. Valid event types: %2', [
            1 => implode(', ', array_intersect_key($invalidLabels, array_flip($invalidEventTypes))),
            2 => implode(', ', array_slice($allowedLabels, 0, 5)) . (count($allowedLabels) > 5 ? '...' : '')
          ]);
        }
      } else {
        $errors['start_action_date'] = E::ts('Registration Expiry Date custom field not found');
      }
    }
  }
}


function cilb_exam_registration_civicrm_tabset($tabsetName, &$tabs, $context) {
  // Only modify contact view tabs.
  if ($tabsetName !== 'civicrm/contact/view') {
    return;
  }

  // Get current Drupal user.
  $current_user = \Drupal::currentUser();
  $roles = $current_user->getRoles();

  $has_staff_role = $current_user->hasRole('staff');

  // Check if user has staff role.
  if ($has_staff_role) {
    foreach ($tabs as $key => $tab) {
      if (in_array($tab['id'] ?? '', ['group'])) {
        unset($tabs[$key]);
      }
    }
  }
  foreach ($tabs as $key => $tab) {
    if (in_array($tab['id'] ?? '', ['tag', 'rel'])) {
      unset($tabs[$key]);
    }
  }
  $desired_order = [
    'summary',    // 1. Summary (weight 0)
    'participant', // 2. Exams (weight 10)
    'log',        // 3. Change Log (weight 20)
    'payments1',  // 4. Payments (weight 30)
    'activity',   // 5. Activities (weight 40)
    'note',       // 6. Notes (weight 50)
    'group'       // 7. Groups (weight 60)
  ];

  foreach ($tabs as $key => $tab) {
    $tab_id = $tab['id'] ?? '';

    // Set exact weights for desired order
    $new_weights = [
      'summary'    => 0,
      'participant' => 10,
      'log'        => 20,
      'payments1'  => 30,
      'activity'   => 40,
      'note'       => 50,
      'group'      => 60
    ];

    if (isset($new_weights[$tab_id])) {
      $tabs[$key]['weight'] = $new_weights[$tab_id];
    }
  }
}

/**
 * Implements hook_civicrm_custom().
 */
function cilb_exam_registration_civicrm_custom($op, $groupID, $entityID, &$params) {
  if (!in_array($op, ['create', 'edit'])) {
    return;
  }

  // Only process our group (Registrant_Info = ID 1 from your debug)
  if ($groupID != 1) {
    return;
  }

  $ssnCol = 'ssn_5';
  $last4Col = 'ssn_last_4_95';
  $tableName = 'civicrm_value_registrant_in_1';
  $ssnValue = '';

  // Loop through params to find SSN field
  foreach ($params as $field) {
    if ($field['column_name'] === $ssnCol) {
      $ssnValue = (string) $field['value'];
      break;
    }
  }

  $last4 = strlen($ssnValue) >= 4 ? substr($ssnValue, -4) : '';

  // Update/create the record
  add_update_ssn_last_4($tableName, $entityID, $ssnCol, $last4Col, $ssnValue, $last4);
}

/**
 * Helper function to handle INSERT/UPDATE safely
 */
function add_update_ssn_last_4($tableName, $entityID, $ssnCol, $last4Col, $ssnValue, $last4Value) {
  // Check if record exists
  $existsSql = "SELECT id FROM {$tableName} WHERE entity_id = %1";
  $existsDao = CRM_Core_DAO::executeQuery($existsSql, [1 => [$entityID, 'Integer']]);

  if ($existsDao->fetch()) {
    // UPDATE existing record
    $sql = "UPDATE {$tableName} SET {$last4Col} = %1 WHERE entity_id = %2";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$last4Value, 'String'],
      2 => [$entityID, 'Integer']
    ]);
  } else {
    // INSERT new record with all required fields
    $sql = "INSERT INTO {$tableName} (entity_id, {$ssnCol}, {$last4Col}) VALUES (%1, %2, %3)";
    CRM_Core_DAO::executeQuery($sql, [
      1 => [$entityID, 'Integer'],
      2 => [$ssnValue, 'String'],
      3 => [$last4Value, 'String']
    ]);
  }
}

function cilb_exam_registration_civicrm_postProcess($formName, $form) {
  if ($formName == 'CRM_Contact_Form_Inline_CustomData') {
    $params = $form->getSubmittedValues();
    $cfID = \Civi\Api4\CustomField::get(FALSE)
                  ->addWhere('name', '=', 'Exam_Language_Preference')
                  ->addWhere('custom_group_id:name', '=', 'Registrant_Info')
                  ->execute()->first()['id'];
    foreach ($params as $key => $value) {
      if ($cfID == CRM_Core_BAO_CustomField::getKeyID($key)) {
        $contactID = $form->getContactID();
        if ($ufID = CRM_Core_BAO_UFMatch::getUFId($contactID)) {
          $user = \Drupal::currentUser()->isAuthenticated() ? User::load($ufID) : NULL;
          $mapper = [1 => 'en', 2 => 'es'];
          $user?->set('preferred_langcode', ($mapper[$value] ?? 'en'))->save();
        }
      }
    }
  }
}

function cilb_exam_registration_civicrm_alterMailParams(&$params, $context) {
   if (in_array($params['valueName'], ['contribution_online_receipt', 'contribution_invoice_receipt'])) {
     $participants = \Civi\Api4\Participant::get(FALSE)
       ->addSelect('event_id.Exam_Details.Exam_Part:label', 'event_id.event_type_id:label', 'event_id.title')
       ->addWhere('Participant_Webform.Candidate_Payment', '=', $params['tplParams']['contributionID'])
       ->addWhere('contact_id', '=', $params['contactId'])
       ->execute();
     $events = [];
     foreach ($participants as $participant) {
       $events[$participant['id']] = [
         'exam_part' => $participant['event_id.Exam_Details.Exam_Part:label'],
         'exam_category' => ($participant['event_id.event_type_id:label'] === 'Business and Finance') ? '' : $participant['event_id.event_type_id:label'],
       ];
     }
     $params['tplParams']['events'] = $events;
   }
}

function _cilb_exam_registration_external_identifier_set(\Civi\Core\Event\PreEvent $event) {
  if ($event->action == 'edit' && \Civi\Api4\Utils\CoreUtil::isContact($event->entity)) {
    $params = $event->params;
    $contactID = $params['id'] ?? $params['contact_id'] ?? NULL;
    if ($contactID) {
      if ($ufID = CRM_Core_BAO_UFMatch::getUFId($contactID)) {
        $user = \Drupal::currentUser()->isAuthenticated() ? User::load($ufID) : NULL;
        if (!empty($params['preferred_language'])) {
          $langcode = strstr($params['preferred_language'], 'es_') ? 'es' : 'en';
          $user?->set('preferred_langcode', $langcode)->save();
        }
        elseif (!empty($params['custom'])) {
          $cfID = \Civi\Api4\CustomField::get(FALSE)
                  ->addWhere('name', '=', 'Exam_Language_Preference')
                  ->addWhere('custom_group_id:name', '=', 'Registrant_Info')
                  ->execute()->first()['id'];
          foreach ($params['custom'] as $id => $value) {
            foreach ($value as $data) {
              if (!empty($data['custom_field_id'] == $cfID)) {
                $mapper = [1 => 'en', 2 => 'es'];
                $user = \Drupal::currentUser()->isAuthenticated() ? User::load($ufID) : NULL;
                $user?->set('preferred_langcode', $mapper[$data['value']])->save();
              }
            }
          }
        }
      }
    }
  }
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
