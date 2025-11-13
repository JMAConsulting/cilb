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
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */
class CRM_AdvancedEvents_Page_RecurringEntityPreview extends CRM_Core_Page {

  /**
   * Run the basic page (run essentially starts execution for that page).
   */
  public function run() {
    $dates = $original = [];
    $formValues = $_REQUEST;
    if (!empty($formValues['entity_table'])) {
      $startDateColumnName = 'start_date';
      $endDateColumnName = 'end_date';

      $recursion = new CRM_AdvancedEvents_BAO_RecurringEntity();
      $recursion->dateColumns = ['start_date'];
      $recursion->scheduleFormValues = $formValues;
      if (!empty($formValues['exclude_date_list'])) {
        $recursion->excludeDates = explode(',', $formValues['exclude_date_list']);
      }
      $recursion->excludeDateRangeColumns = ['start_date', 'end_date'];

      // Get original entity
      $original[$startDateColumnName] = CRM_Utils_Date::processDate($formValues['repetition_start_date']);
      if ($formValues['entity_table'] === 'civicrm_event' && !empty($formValues['entity_id'])) {
        $event = \Civi\Api4\Event::get(FALSE)
          ->addSelect('end_date')
          ->addWhere('id', '=', $formValues['entity_id'])
          ->execute()
          ->first();
        if (!empty($event['end_date'])) {
          $interval = $recursion->getInterval($original[$startDateColumnName], $event['end_date']);
          $recursion->intervalDateColumns = [$endDateColumnName => $interval];
          $original[$endDateColumnName] = CRM_Utils_Date::processDate($event['end_date']);
        }
      }

      //Check if there is any enddate column defined to find out the interval between the two
      $dates = $recursion->generateRecursiveDates();

      foreach ($dates as $key => &$value) {
        if ($startDateColumnName) {
          $value['start_date'] = CRM_Utils_Date::customFormat($value[$startDateColumnName]);
        }
        if ($endDateColumnName && !empty($value[$endDateColumnName])) {
          $value['end_date'] = CRM_Utils_Date::customFormat($value[$endDateColumnName]);
          $endDates = TRUE;
        }
      }

      // Show the list of participants registered for the events if any
      if ($formValues['entity_table'] == "civicrm_event" && !empty($parentEntityId)) {
        $getConnectedEntities = CRM_AdvancedEvents_BAO_RecurringEntity::getEntitiesForParent($parentEntityId, 'civicrm_event', TRUE);
        if ($getConnectedEntities) {
          $participantDetails = CRM_AdvancedEvents_Form_ManageEvent_Repeat::getParticipantCountforEvent($getConnectedEntities);
          if (!empty($participantDetails['countByName'])) {
            $this->assign('participantData', $participantDetails['countByName']);
          }
        }
      }
    }
    $this->assign('dates', $dates);
    $this->assign('endDates', !empty($endDates));

    return parent::run();
  }

}
