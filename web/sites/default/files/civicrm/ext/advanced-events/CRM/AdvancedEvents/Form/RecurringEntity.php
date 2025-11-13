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

use Civi\Api4\ActionSchedule;
use CRM_AdvancedEvents_ExtensionUtil as E;

/**
 * This class generates form components for processing Entity.
 */
class CRM_AdvancedEvents_Form_RecurringEntity {
  /**
   *  Current entity id
   * @var int
   */
  protected static $_entityId = NULL;

  /**
   * Schedule Reminder ID
   * @var int
   */
  protected static $_scheduleReminderID = NULL;

  /**
   * Schedule Reminder data
   * @var CRM_Core_DAO|null
   */
  protected static $_scheduleReminderDetails = NULL;

  /**
   *  Parent Entity ID
   * @var int
   */
  protected static $_parentEntityId = NULL;

  /**
   * Exclude date information
   * @var array
   */
  public static $_excludeDateInfo = [];

  /**
   * Set default values for the form. For edit/view mode
   * the default values are retrieved from the database
   *
   *
   * @return array
   */
  public static function setDefaultValues() {
    // Defaults for new entity
    $defaults = [
      'repetition_frequency_unit' => 'week',
    ];

    // Default for existing entity
    if (self::$_scheduleReminderID) {
      $defaults['repetition_frequency_unit'] = self::$_scheduleReminderDetails->repetition_frequency_unit;
      $defaults['repetition_frequency_interval'] = self::$_scheduleReminderDetails->repetition_frequency_interval;
      $defaults['start_action_condition'] = array_flip(explode(",", self::$_scheduleReminderDetails->start_action_condition));
      foreach ($defaults['start_action_condition'] as $key => $val) {
        $val = 1;
        $defaults['start_action_condition'][$key] = $val;
      }
      $defaults['start_action_offset'] = self::$_scheduleReminderDetails->start_action_offset;
      if (self::$_scheduleReminderDetails->start_action_offset) {
        $defaults['ends'] = 1;
      }
      $defaults['repeat_absolute_date'] = self::$_scheduleReminderDetails->absolute_date;
      if (self::$_scheduleReminderDetails->absolute_date) {
        $defaults['ends'] = 2;
      }
      $defaults['limit_to'] = self::$_scheduleReminderDetails->limit_to;
      if (self::$_scheduleReminderDetails->limit_to == 1) {
        $defaults['repeats_by'] = 1;
      }
      if (self::$_scheduleReminderDetails->entity_status) {
        $explodeStartActionCondition = explode(" ", self::$_scheduleReminderDetails->entity_status);
        $defaults['entity_status_1'] = $explodeStartActionCondition[0];
        $defaults['entity_status_2'] = $explodeStartActionCondition[1];
      }
      if (self::$_scheduleReminderDetails->entity_status) {
        $defaults['repeats_by'] = 2;
      }
      if (self::$_excludeDateInfo) {
        $defaults['exclude_date_list'] = implode(',', self::$_excludeDateInfo);
      }
    }
    return $defaults;
  }

  /**
   * Build form.
   *
   * @param CRM_Core_Form $form
   */
  public static function buildQuickForm(&$form) {
    // FIXME: this is using the following as keys rather than the standard numeric keys returned by CRM_Utils_Date
    $dayOfTheWeek = [];
    $dayKeys = [
      'sunday',
      'monday',
      'tuesday',
      'wednesday',
      'thursday',
      'friday',
      'saturday',
    ];
    foreach (CRM_Utils_Date::getAbbrWeekdayNames() as $k => $label) {
      $dayOfTheWeek[$dayKeys[$k]] = $label;
    }
    $form->add('select', 'repetition_frequency_unit', E::ts('Repeats every'), CRM_Core_SelectValues::getRecurringFrequencyUnits(), FALSE, ['class' => 'required']);
    $numericOptions = CRM_Core_SelectValues::getNumericOptions(1, 30);
    $form->add('select', 'repetition_frequency_interval', NULL, $numericOptions, FALSE, ['class' => 'required']);
    $form->add('datepicker', 'repetition_start_date', E::ts('Start Date'), [], FALSE, ['time' => TRUE]);
    foreach ($dayOfTheWeek as $key => $val) {
      $startActionCondition[] = $form->createElement('checkbox', $key, NULL, $val);
    }
    $form->addGroup($startActionCondition, 'start_action_condition', E::ts('Repeats on'));
    $roptionTypes = [
      '1' => E::ts('day of the month'),
      '2' => E::ts('day of the week'),
    ];
    $form->addRadio('repeats_by', E::ts("Repeats on"), $roptionTypes, ['required' => TRUE], NULL);
    $form->add('select', 'limit_to', '', CRM_Core_SelectValues::getNumericOptions(1, 31));
    $dayOfTheWeekNo = [
      'first' => E::ts('First'),
      'second' => E::ts('Second'),
      'third' => E::ts('Third'),
      'fourth' => E::ts('Fourth'),
      'last' => E::ts('Last'),
    ];
    $form->add('select', 'entity_status_1', '', $dayOfTheWeekNo);
    $form->add('select', 'entity_status_2', '', $dayOfTheWeek);
    $eoptionTypes = [
      '1' => E::ts('After'),
      '2' => E::ts('On'),
    ];
    $form->addRadio('ends', E::ts("Ends"), $eoptionTypes, ['class' => 'required'], NULL);
    // Offset options gets key=>val pairs like 1=>2 because the BAO wants to know the number of
    // children while it makes more sense to the user to see the total number including the parent.
    $offsetOptions = range(1, 30);
    unset($offsetOptions[0]);
    $form->add('select', 'start_action_offset', NULL, $offsetOptions, FALSE);
    $form->addFormRule(['CRM_AdvancedEvents_Form_RecurringEntity', 'formRule']);
    $form->add('datepicker', 'repeat_absolute_date', E::ts('On'), [], FALSE, ['time' => FALSE]);
    $form->add('text', 'exclude_date_list', E::ts('Exclude Dates'), ['class' => 'twenty']);
    $form->addElement('hidden', 'allowRepeatConfigToSubmit', '', ['id' => 'allowRepeatConfigToSubmit']);
    $form->addButtons([
        [
          'type' => 'submit',
          'name' => E::ts('Create'),
          'isDefault' => TRUE,
        ],
      ]
    );
    // For client-side pluralization
    $form->assign('recurringFrequencyOptions', [
      'single' => CRM_Utils_Array::makeNonAssociative(CRM_Core_SelectValues::getRecurringFrequencyUnits()),
      'plural' => CRM_Utils_Array::makeNonAssociative(CRM_Core_SelectValues::getRecurringFrequencyUnits(2)),
    ]);
  }

  /**
   * Global validation rules for the form.
   *
   * @param array $values
   *   Posted values of the form.
   *
   * @return array
   *   list of errors to be posted back to the form
   */
  public static function formRule($values) {
    $errors = [];
    //Process this function only when you get this variable
    if ($values['allowRepeatConfigToSubmit'] == 1) {
      $dayOfTheWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
      //Repeats
      if (empty($values['repetition_frequency_unit'])) {
        $errors['repetition_frequency_unit'] = E::ts('This is a required field');
      }
      //Repeats every
      if (empty($values['repetition_frequency_interval'])) {
        $errors['repetition_frequency_interval'] = E::ts('This is a required field');
      }
      //Ends
      if (!empty($values['ends'])) {
        if ($values['ends'] == 1) {
          if (empty($values['start_action_offset'])) {
            $errors['start_action_offset'] = E::ts('This is a required field');
          }
          elseif ($values['start_action_offset'] > 30) {
            $errors['start_action_offset'] = E::ts('Occurrences should be less than or equal to 30');
          }
        }
        if ($values['ends'] == 2) {
          if (!empty($values['repeat_absolute_date'])) {
            $entityStartDate = CRM_Utils_Date::processDate($values['repetition_start_date']);
            $end = CRM_Utils_Date::processDate($values['repeat_absolute_date']);
            if (($end < $entityStartDate) && ($end != 0)) {
              $errors['repeat_absolute_date'] = E::ts('End date should be after current entity\'s start date');
            }
          }
          else {
            $errors['repeat_absolute_date'] = E::ts('This is a required field');
          }
        }
      }
      else {
        $errors['ends'] = E::ts('This is a required field');
      }

      //Repeats BY
      if (!empty($values['repeats_by'])) {
        if ($values['repeats_by'] == 1) {
          if (!empty($values['limit_to'])) {
            if ($values['limit_to'] < 1 && $values['limit_to'] > 31) {
              $errors['limit_to'] = E::ts('Invalid day of the month');
            }
          }
          else {
            $errors['limit_to'] = E::ts('Invalid day of the month');
          }
        }
        if ($values['repeats_by'] == 2) {
          if (!empty($values['entity_status_1'])) {
            $dayOfTheWeekNo = ['first', 'second', 'third', 'fourth', 'last'];
            if (!in_array($values['entity_status_1'], $dayOfTheWeekNo)) {
              $errors['entity_status_1'] = E::ts('Invalid option');
            }
          }
          else {
            $errors['entity_status_1'] = E::ts('Invalid option');
          }
          if (!empty($values['entity_status_2'])) {
            if (!in_array($values['entity_status_2'], $dayOfTheWeek)) {
              $errors['entity_status_2'] = E::ts('Invalid day name');
            }
          }
          else {
            $errors['entity_status_2'] = E::ts('Invalid day name');
          }
        }
      }
    }
    return $errors;
  }

  /**
   * Process the form submission.
   *
   * @param array $params
   *
   * @throws \Exception
   */
  public static function postProcess($params = []) {
    // Check entity_id not present in params take it from class variable
    if (empty($params['entity_id'])) {
      $params['entity_id'] = self::$_entityId;
    }
    //Process this function only when you get this variable
    if (($params['allowRepeatConfigToSubmit'] ?? NULL) == 1) {
      if (!empty($params['entity_table']) && !empty($params['entity_id']) && 'civicrm_event') {
        $params['used_for'] = 'civicrm_event';
        if (empty($params['parent_entity_id'])) {
          $params['parent_entity_id'] = self::$_parentEntityId;
        }

        $actionScheduleName = 'repeat_civicrm_event_' . $params['entity_id'];
        ActionSchedule::delete(FALSE)
          ->addWhere('name', '=', $actionScheduleName)
          ->execute();

        // Save post params to the schedule reminder table
        $recurobj = new CRM_AdvancedEvents_BAO_RecurringEntity();
        $dbParams = $recurobj->mapFormValuesToDB($params);
        $dbParams['name'] = $actionScheduleName;

        //Delete repeat configuration and rebuild
        unset($params['id']);
        $actionScheduleObj = CRM_Core_BAO_ActionSchedule::writeRecord($dbParams);

        //exclude dates
        $excludeDateList = [];
        if (!empty($params['exclude_date_list']) && !empty($params['parent_entity_id']) && $actionScheduleObj->entity_value) {
          //Since we get comma separated values lets get them in array
          $excludeDates = explode(",", $params['exclude_date_list']);

          //Check if there exists any values for this option group
          $optionGroupIdExists = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_OptionGroup',
            'civicrm_event' . '_repeat_exclude_dates_' . $params['parent_entity_id'],
            'id',
            'name'
          );
          if ($optionGroupIdExists) {
            CRM_Core_BAO_OptionGroup::deleteRecord(['id' => $optionGroupIdExists]);
          }
          $optionGroupParams = [
            'name' => 'civicrm_event' . '_repeat_exclude_dates_' . $actionScheduleObj->entity_value,
            'title' => 'civicrm_event' . ' recursion',
            'is_reserved' => 0,
            'is_active' => 1,
          ];
          $opGroup = CRM_Core_BAO_OptionGroup::add($optionGroupParams);
          if ($opGroup->id) {
            $oldWeight = 0;
            $fieldValues = ['option_group_id' => $opGroup->id];
            foreach ($excludeDates as $val) {
              $optionGroupValue = [
                'option_group_id' => $opGroup->id,
                'label' => CRM_Utils_Date::processDate($val),
                'value' => CRM_Utils_Date::processDate($val),
                'name' => $opGroup->name,
                'description' => 'Used for recurring ' . 'civicrm_event',
                'weight' => CRM_Utils_Weight::updateOtherWeights('CRM_Core_DAO_OptionValue', $oldWeight, $params['weight'] ?? NULL, $fieldValues),
                'is_active' => 1,
              ];
              $excludeDateList[] = $optionGroupValue['value'];
              CRM_Core_BAO_OptionValue::create($optionGroupValue);
            }
          }
        }

        $recursion = new CRM_AdvancedEvents_BAO_RecurringEntity();
        $recursion->dateColumns = ['start_date'];
        $recursion->scheduleId = $actionScheduleObj->id;

        if (!empty($excludeDateList)) {
          $recursion->excludeDates = $excludeDateList;
          $recursion->excludeDateRangeColumns = ['start_date', 'end_date'];
        }
        if (!empty($params['intervalDateColumns'])) {
          $recursion->intervalDateColumns = $params['intervalDateColumns'];
        }
        $recursion->entity_id = $params['entity_id'];
        $recursion->generate();

        $status = E::ts('Repeat Configuration has been saved');
        CRM_Core_Session::setStatus($status, E::ts('Saved'), 'success');
      }
    }
  }

  /**
   * Return a descriptive name for the page, used in wizard header
   *
   * @return string
   */
  public function getTitle() {
    return E::ts('Repeat Event');
  }

}
