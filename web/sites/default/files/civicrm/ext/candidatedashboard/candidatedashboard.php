<?php

require_once 'candidatedashboard.civix.php';

use CRM_Candidatedashboard_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function candidatedashboard_civicrm_config(&$config): void {
  _candidatedashboard_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function candidatedashboard_civicrm_install(): void {
  _candidatedashboard_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function candidatedashboard_civicrm_enable(): void {
  _candidatedashboard_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_pageRun().
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pagerun/
 */
function candidatedashboard_civicrm_pageRun( &$page) {
  if ($page->getVar('_name') === 'CRM_Contact_Page_View_UserDashBoard') {
    Civi::service('angularjs.loader')->addModules('afsearchFindActivities');
    $html = '<crm-angular-js modules="afsearchFindActivities"><form id="bootstrap-theme"><afsearch-find-activities></afsearch-find-activities></form></crm-angular-js>';
    CRM_Core_Region::instance('crm-activity-userdashboard-post')->add([
     'markup' => $html, 'weight' => 10
    ]);
    CRM_Core_Resources::singleton()->addScript(
      "CRM.$(function($) {
        $('.crm-dashboard-assignedActivities table').hide();
      });"
    );

    $dashboardElements = $page->get_template_vars('dashboardElements');
    foreach ($dashboardElements as $key => $dashboardElement) {
      if ($dashboardElement['class'] == 'crm-dashboard-assignedActivities') {
        $dashboardElements[$key]['sectionTitle'] = E::ts('Your Activities');
        break;
      }
    }
    $page->assign('dashboardElements', $dashboardElements);

    $participantRecords = $page->get_template_vars('event_rows');
    foreach ($participantRecords as $k => $record) {
       $participantRecords[$k]['score'] = \Civi\Api4\Participant::get(FALSE)
          ->addSelect('Candidate_Result.Candidate_Score')
          ->addWhere('id', '=', $record['id'])
          ->execute()
          ->first()['Candidate_Result.Candidate_Score'];
    }
    $page->assign('event_rows', $participantRecords);

    // Personal info
    $contactId = CRM_Core_Session::singleton()->getLoggedInContactID();
    $contact = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('id', '=', $contactId)
      ->execute()->first();
    $phone = \Civi\Api4\Phone::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->execute()->column('phone');
    $email = \Civi\Api4\Email::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->execute()->first()['email'];
    $address = \Civi\Api4\Address::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_primary', '=', TRUE)
      ->execute()->first();
    if ($address['state_province_id']) {
      $state = \Civi\Api4\StateProvince::get(FALSE)
        ->addwhere('id', '=', $address['state_province_id'])
        ->execute()->first()['name'];
    }
    else {
      $state = NULL;
    }
    if (!empty($address['country_id'])) {
      $country = \Civi\Api4\Country::get(FALSE)
        ->addwhere('id', '=', $address['country_id'])
        ->execute()->first()['name'];
    }
    else {
      $country = NULL;
    }
    // Variables to pass to the template
    $personalInfo = [
      'first_name' => $contact['first_name'],
      'last_name' => $contact['last_name'],
      'email' => $email,
      'phone_numbers' => $phone
    ];
    $addressFields = [
      'street_address' => $address['street_address'],
      'supplemental_address_1' => $address['supplemental_address_1'],
      'supplemental_address_2'=> $address['supplemental_address_2'],
      'supplemental_address_3' => $address['supplemental_address_3'],
      'city' => $address['city'],
      'state' => $state,
      'country' => $country,
      'postal_code' => $address['postal_code']
    ];
    $page->assign('personal_rows', $personalInfo);
    $page->assign('address_rows', $addressFields);

    // Activities
    $activities = \Civi\Api4\Activity::get(FALSE)
      ->addSelect('*', 'source_contact_id', 'target_contact_id', 'assignee_contact_id', 'status_id:label')
      ->addClause('OR', ['assignee_contact_id', '=', $contactId], ['target_contact_id', '=', $contactId])
      ->execute();
    $activityRows= [];
    foreach($activities as $activity) {
      if (isset($activity['activity_type_id'])) {
        $activityType = \Civi\Api4\OptionValue::get(FALSE)
          ->addWhere('option_group_id:name', '=', 'activity_type')
          ->addWhere('value', '=', $activity['activity_type_id'])
          ->execute()->first()['label'];
      }
      else {
        $activityType = NULL;
      }
      if (isset($activity['source_contact_id'])) {
        $sourceContactName = \Civi\Api4\Contact::get(FALSE)
          ->addWhere('id', '=', $activity['source_contact_id'])
          ->execute()->first()['display_name'];
      }
      else {
        $sourceContactName = NULL;
      }
      if (isset($activity['target_contact_id'])) {
        $targets = [];
        foreach($activity['target_contact_id'] as $targetId) {
          $targetName = \Civi\Api4\Contact::get(FALSE)
            ->addWhere('id', '=', $targetId)
            ->execute()->first()['display_name'];
          $targets[$targetId] = $targetName;
        }
      }
      else {
        $targets = NULL;
      }
      if(isset($activity['status_id']))
      $row = [
        'activity_id' => $activity['id'],
        'activity_type' => $activityType,
        'contact_id' => $activity['source_contact_id'],
        'activity_subject' => $activity['subject'],
        'source_contact_id' => $activity['source_contact_id'],
        'source_contact_name' => $sourceContactName,
        'target_contact_name' => $targets,
        'activity_date_time' => $activity['activity_date_time'],
        'activity_status' => $activity['status_id:label']
      ];
      $activityRows[] = $row;
    }
    $page->assign('activity_rows', $activityRows);

    // Notes
    /*
    $notes = \Civi\Api4\Note::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->execute();
    $page->assign('notes', $notes);
    */ 
  }
}


