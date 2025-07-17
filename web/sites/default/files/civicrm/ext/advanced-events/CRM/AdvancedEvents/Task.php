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

/**
 * class to represent the actions that can be performed on a group of contacts
 * used by the search forms
 *
 */
class CRM_AdvancedEvents_Task extends CRM_Core_Task {

  static $objectType = 'event';

  const TASK_COPYPARTICIPANTS = 600;

  /**
   * These tasks are the core set of tasks that the user can perform
   * on a contact / group of contacts
   *
   * @return array The set of tasks for a group of contacts
   *            [ 'title' => The Task title,
   *              'class' => The Task Form class name,
   *              'result => Boolean.  FIXME: Not sure what this is for
   *            ]
   */
  public static function tasks() {
    if (!self::$_tasks) {
      self::$_tasks = [
        self::TASK_COPYPARTICIPANTS => [
          'title' => E::ts('Copy participants'),
          'class' => 'CRM_AdvancedEvents_Form_Task_CopyParticipants',
          'result' => TRUE,
        ],
      ];

      //CRM-12920 - check for edit permission
      if (!CRM_Core_Permission::check('edit event participants')) {
        unset(self::$_tasks[self::BATCH_UPDATE], self::$_tasks[self::TASK_COPYPARTICIPANTS]);
      }

      parent::tasks();
    }

    return self::$_tasks;
  }

  /**
   * Show tasks selectively based on the permission level
   * of the user
   *
   * @param int $permission
   * @param array $params
   *
   * @return array
   *   set of tasks that are valid for the user
   */
  public static function permissionedTaskTitles($permission, $params = []) {
    if (($permission == CRM_Core_Permission::EDIT)
      || CRM_Core_Permission::check('edit event participants')
    ) {
      $tasks = self::taskTitles();
    }
    else {
      $tasks = [
        self::TASK_EXPORT => self::$_tasks[self::TASK_EXPORT]['title'],
        self::TASK_EMAIL => self::$_tasks[self::TASK_EMAIL]['title'],
      ];
    }

    $tasks = parent::corePermissionedTaskTitles($tasks, $permission, $params);
    return $tasks;
  }

  /**
   * These tasks are the core set of tasks that the user can perform
   * on participants
   *
   * @param int $value
   *
   * @return array
   *   the set of tasks for a group of participants
   */
  public static function getTask($value) {
    self::tasks();
    if (!$value || !(self::$_tasks[$value] ?? NULL)) {
      // make the print task by default
      $value = self::TASK_PRINT;
    }
    return parent::getTask($value);
  }

}
