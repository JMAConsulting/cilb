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
    // Personal info
    $contactId = CRM_Core_Session::singleton()->getLoggedInContactID();
    $contact = \Civi\Api4\Contact::get()
      ->addWhere('id', '=', $contactId)
      ->execute()->first();
    $phone = \Civi\Api4\Phone::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->execute()->column('phone');
    $email = \Civi\Api4\Email::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->execute()->first()['email'];
    $address = \Civi\Api4\Address::get()
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


    // Notes
    $notes = \Civi\Api4\Note::get()
      ->addWhere('contact_id', '=', $contactId)
      ->execute();
    $page->assign('notes', $notes); 
  }
}


