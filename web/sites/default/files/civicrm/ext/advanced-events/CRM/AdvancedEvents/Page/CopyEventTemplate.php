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

/**
 * Page for displaying list of event templates.
 */
class CRM_AdvancedEvents_Page_CopyEventTemplate extends CRM_Core_Page {

  /**
   * Run the page.
   *
   * @return void
   * @throws \CRM_Core_Exception
   */
  public function run() {
    if (!CRM_Core_Permission::check('edit own event templates')
      && !CRM_Core_Permission::check('edit all event templates')) {
      CRM_Core_Error::statusBounce(E::ts('You do not have edit permissions for event templates.'));
    }

    $id = CRM_Utils_Request::retrieve('id', 'Positive', $this, TRUE, 0, 'GET');

    $urlString = 'civicrm/event/manage';
    $copyEvent = civicrm_api3('EventTemplate', 'create', ['event_id' => $id]);
    $urlParams = 'reset=1';
    // Redirect to Copied Event Configuration
    if ($copyEvent['id']) {
      $urlString = 'civicrm/event/manage/settings';
      $urlParams .= '&action=update&id=' . $copyEvent['id'];
    }

    CRM_Utils_System::redirect(CRM_Utils_System::url($urlString, $urlParams));
  }

}
