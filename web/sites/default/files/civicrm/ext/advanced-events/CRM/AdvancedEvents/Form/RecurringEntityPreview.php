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

class CRM_AdvancedEvents_Form_RecurringEntityPreview extends CRM_Core_Form {

  /**
   * Return a descriptive name for the page, used in wizard header
   *
   * @return string
   */
  public function getTitle() {
    return E::ts('Repeat Event');
  }

  /**
   * Build form.
   *
   */
  public function buildQuickForm() {
    $startDate = $endDate = NULL;
    $dates = $original = [];
    $formValues = $_REQUEST;
    if (!empty($formValues['entity_table'])) {
      $startDateColumnName = CRM_AdvancedEvents_BAO_RecurringEntity::$_dateColumns[$formValues['entity_table']]['dateColumns'][0];
      $endDateColumnName = CRM_AdvancedEvents_BAO_RecurringEntity::$_dateColumns[$formValues['entity_table']]['intervalDateColumns'][0];

      $recursion = new CRM_AdvancedEvents_BAO_RecurringEntity();
      if (CRM_AdvancedEvents_BAO_RecurringEntity::$_dateColumns[$formValues['entity_table']]['dateColumns'] ?? NULL) {
        $recursion->dateColumns = CRM_AdvancedEvents_BAO_RecurringEntity::$_dateColumns[$formValues['entity_table']]['dateColumns'];
      }
      $recursion->scheduleFormValues = $formValues;
      if (!empty($formValues['exclude_date_list'])) {
        $recursion->excludeDates = explode(',', $formValues['exclude_date_list']);
      }
      if (CRM_AdvancedEvents_BAO_RecurringEntity::$_dateColumns[$formValues['entity_table']]['excludeDateRangeColumns'] ?? NULL) {
        $recursion->excludeDateRangeColumns = CRM_AdvancedEvents_BAO_RecurringEntity::$_dateColumns[$formValues['entity_table']]['excludeDateRangeColumns'];
      }

      // Get original entity
      $original[$startDateColumnName] = $formValues['repetition_start_date'];
      $daoName = CRM_AdvancedEvents_BAO_RecurringEntity::$_tableDAOMapper[$formValues['entity_table']];
      if ($formValues['entity_id']) {
        $startDate = $original[$startDateColumnName] = CRM_Core_DAO::getFieldValue($daoName, $formValues['entity_id'], $startDateColumnName);
        $endDate = $original[$startDateColumnName] = $endDateColumnName ? CRM_Core_DAO::getFieldValue($daoName, $formValues['entity_id'], $endDateColumnName) : NULL;
      }

      //Check if there is any enddate column defined to find out the interval between the two range
      if (CRM_AdvancedEvents_BAO_RecurringEntity::$_dateColumns[$formValues['entity_table']]['intervalDateColumns'] ?? NULL) {
        if ($endDate) {
          $interval = $recursion->getInterval($startDate, $endDate);
          $recursion->intervalDateColumns = [$endDateColumnName => $interval];
        }
      }

      $dates = $recursion->generateRecursiveDates();

      foreach ($dates as $key => &$value) {
        if ($startDateColumnName) {
          if (CRM_AdvancedEvents_BAO_EventTemplate::eventAlreadyExists($formValues['entity_id'], ['start_date' => $value[$startDateColumnName]])) {
            $value['exists'] = TRUE;
          }
        }
        if ($endDateColumnName && !empty($value[$endDateColumnName])) {
          $endDates = TRUE;
        }
      }

    }
    $this->assign('dates', $dates);
    $this->assign('endDates', !empty($endDates));
  }

}
