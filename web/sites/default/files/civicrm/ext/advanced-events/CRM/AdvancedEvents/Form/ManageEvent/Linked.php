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
 * Class to manage the "Linked Events" functionality for Event Template
 */
class CRM_AdvancedEvents_Form_ManageEvent_Linked extends CRM_Event_Form_ManageEvent {

  public function preProcess() {
    parent::preProcess();
    $eventID = CRM_Utils_Request::retrieve('template_id', 'Positive', $this, FALSE, NULL, 'GET');
    $this->assign('templateId', $eventID);
  }

}
