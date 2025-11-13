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
 * Class for event form task actions.
 * FIXME: This needs refactoring to properly inherit from CRM_Core_Form_Task and share more functions.
 */
class CRM_AdvancedEvents_Form_Task extends CRM_Core_Form_Task {

  /**
   * The array that holds all the participant ids.
   *
   * @var array
   */
  protected $_eventIds;

  /**
   * Build all the data structures needed to build the form.
   *
   * @param
   *
   * @return void
   */
  public function preProcess() {
    self::preProcessCommon($this);
  }

  /**
   * @param CRM_Core_Form $form
   */
  public static function preProcessCommon(&$form) {
    $form->_eventIds = [];

    $values = $form->controller->exportValues($form->get('searchFormName'));

    $isStandAlone = in_array('task', $form->urlPath) || in_array('standalone', $form->urlPath) || in_array('map', $form->urlPath);
    if ($isStandAlone) {
      [$form->_task, $title] = CRM_Event_Task::getTaskAndTitleByClass(get_class($form));
      if (!array_key_exists($form->_task, CRM_Event_Task::permissionedTaskTitles(CRM_Core_Permission::getPermission()))) {
        CRM_Core_Error::statusBounce(E::ts('You do not have permission to access this page.'));
      }
      $form->_eventIds = explode(',', CRM_Utils_Request::retrieve('cids', 'CommaSeparatedIntegers', $form, TRUE));
      if (empty($form->_eventIds)) {
        CRM_Core_Error::statusBounce(E::ts('No Contacts Selected'));
      }
      $form->setTitle($title);
    }

    if (!empty($form->_eventIds)) {
      $form->_componentClause = ' civicrm_event.id IN ( ' . implode(',', $form->_eventIds) . ' ) ';
      $form->assign('totalSelectedEvents', count($form->_eventIds));
    }
  }

  /**
   * Given the participant id, compute the contact id
   * since its used for things like send email
   */
  public function setContactIDs() {
    $this->_contactIds = NULL;
  }

  /**
   * Simple shell that derived classes can call to add buttons to.
   * the form with a customized title for the main Submit
   *
   * @param string $title
   *   Title of the main button.
   * @param string $nextType
   * @param string $backType
   * @param bool $submitOnce
   *
   * @return void
   */
  public function addDefaultButtons($title, $nextType = 'next', $backType = 'back', $submitOnce = FALSE) {
    $this->addButtons([
        [
          'type' => $nextType,
          'name' => $title,
          'isDefault' => TRUE,
        ],
        [
          'type' => $backType,
          'name' => E::ts('Cancel'),
        ],
      ]
    );
  }

}
