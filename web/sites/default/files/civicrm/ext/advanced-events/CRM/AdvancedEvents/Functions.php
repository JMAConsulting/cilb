<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use CRM_AdvancedEvents_ExtensionUtil as E;

class CRM_AdvancedEvents_Functions {

  public static function getEnabled() {
    return [
      'location' => \Civi::settings()->get('advanced_events_function_location'),
      'fee' => \Civi::settings()->get('advanced_events_function_fee'),
      'registration' => \Civi::settings()->get('advanced_events_function_registration'),
      'reminder' => \Civi::settings()->get('advanced_events_function_reminder'),
      'friend' => \Civi::settings()->get('advanced_events_function_friend'),
      'pcp' => \Civi::settings()->get('advanced_events_function_pcp'),
      'repeat' => \Civi::settings()->get('advanced_events_function_repeat'),
    ];
  }
}
