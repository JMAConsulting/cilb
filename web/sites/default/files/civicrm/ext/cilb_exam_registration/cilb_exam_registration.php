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
         'exam_category' => $participant['event_id.event_type_id:label'],
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
