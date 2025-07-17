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

use When\When;

/**
 * Class CRM_AdvancedEvents_BAO_RecurringEntity.
 */
class CRM_AdvancedEvents_BAO_RecurringEntity extends CRM_Core_DAO_RecurringEntity {

  const RUNNING = 1;
  public $schedule = [];
  public $scheduleId = NULL;
  public $scheduleFormValues = [];

  public $dateColumns = [];
  public $intervalDateColumns = [];
  public $excludeDates = [];

  /**
   * @var When
   */
  protected $recursion = NULL;
  protected $recursion_start_date = NULL;

  public static $_entitiesToBeDeleted = [];

  public static $status = NULL;

  static $_dateColumns
    = [
      'civicrm_event' => [
        'dateColumns' => ['start_date'],
        'excludeDateRangeColumns' => ['start_date', 'end_date'],
        'intervalDateColumns' => ['end_date'],
      ],
    ];

  static $_tableDAOMapper
    = [
      'civicrm_event' => 'CRM_Event_DAO_Event',
      'civicrm_price_set_entity' => 'CRM_Price_DAO_PriceSetEntity',
      'civicrm_uf_join' => 'CRM_Core_DAO_UFJoin',
      'civicrm_tell_friend' => 'CRM_Friend_DAO_Friend',
      'civicrm_pcp_block' => 'CRM_PCP_DAO_PCPBlock',
      'civicrm_activity' => 'CRM_Activity_DAO_Activity',
      'civicrm_activity_contact' => 'CRM_Activity_DAO_ActivityContact',
    ];

  /**
   * Getter for status.
   *
   * @return string
   */
  public static function getStatus() {
    return self::$status;
  }

  /**
   * Setter for status.
   *
   * @param string $status
   */
  public static function setStatus($status) {
    self::$status = $status;
  }

  /**
   * This function generates all new entities based on object vars.
   *
   * @return array
   * @throws \Exception
   */
  public function generate() {
    $this->generateRecursiveDates();

    return $this->generateEntities();
  }

  /**
   * This function builds a "When" object based on schedule/reminder params
   *
   * @return object
   *   When object
   */
  public function generateRecursion() {
    // return if already generated
    if (is_a($this->recursion, 'When')) {
      return $this->recursion;
    }

    if ($this->scheduleId) {
      // get params by ID
      $this->schedule = $this->getScheduleParams($this->scheduleId);
    }
    elseif (!empty($this->scheduleFormValues)) {
      $this->schedule = $this->mapFormValuesToDB($this->scheduleFormValues);
    }

    if (!empty($this->schedule)) {
      $this->recursion = $this->getRecursionFromSchedule($this->schedule);
    }
    return $this->recursion;
  }

  /**
   * Generate new DAOs and along with entries in civicrm_recurring_entity table.
   *
   * @return array
   * @throws \Exception
   */
  public function generateEntities() {
    self::setStatus(self::RUNNING);

    $newEntities = [];
    if (!empty($this->recursionDates)) {
      if (empty($this->entity_id)) {
        throw new CRM_Core_Exception("Find criteria missing to generate form. Make sure entity_id and table is set.");
      }
      foreach ($this->recursionDates as $key => $dateCols) {
        $newCriteria = $dateCols;
        if (!CRM_AdvancedEvents_BAO_EventTemplate::eventAlreadyExists($this->entity_id, ['start_date' => $dateCols['start_date']])) {
          // create main entities if they don't already exist
          CRM_AdvancedEvents_BAO_RecurringEntity::copyCreateEntity($this->entity_id, $newCriteria);
        }
      }
    }

    self::$status = NULL;
    return $newEntities;
  }

  /**
   * This function iterates through when object criteria and
   * generates recursive dates based on that
   *
   * @return array
   *   array of dates
   */
  public function generateRecursiveDates() {
    $this->generateRecursion();

    $recursionDates = [];
    if (is_a($this->recursion, 'When\When')) {
      $initialCount = ($this->schedule['start_action_offset'] ?? 0) + 1;

      $exRangeStart = $exRangeEnd = NULL;
      if (!empty($this->excludeDateRangeColumns)) {
        $exRangeStart = $this->excludeDateRangeColumns[0];
        $exRangeEnd = $this->excludeDateRangeColumns[1];
      }

      $count = 0;
      if (empty($this->schedule['absolute_date'])) {
        // When absolute_date is set start_action_offset is missing, and we
        // don't need to set count. Calculation happens based on end-date (absolute_date)
        $this->recursion->count($initialCount);
      }
      try {
        $this->recursion->generateOccurrences();
      }
      catch (Exception $e) {
        CRM_Core_Error::statusBounce($e->getMessage());
        return $recursionDates;
      }
      foreach ($this->recursion->occurrences as $result) {
        $skip = FALSE;
        $baseDate = $result->format('YmdHis');

        foreach ($this->dateColumns as $col) {
          $recursionDates[$count][$col] = $baseDate;
        }
        foreach ($this->intervalDateColumns as $col => $interval) {
          $newDate = new DateTime($baseDate);
          $newDate->add($interval);
          $recursionDates[$count][$col] = $newDate->format('YmdHis');
        }
        if ($exRangeStart) {
          $exRangeStartDate = CRM_Utils_Date::processDate($recursionDates[$count][$exRangeStart] ?? NULL, NULL, FALSE, 'Ymd');
          $exRangeEndDate = CRM_Utils_Date::processDate($recursionDates[$count][$exRangeEnd], NULL, FALSE, 'Ymd');
        }

        foreach ($this->excludeDates as $exDate) {
          $exDate = CRM_Utils_Date::processDate($exDate, NULL, FALSE, 'Ymd');
          if (!$exRangeStart) {
            if ($exDate == $result->format('Ymd')) {
              $skip = TRUE;
              break;
            }
          }
          else {
            if (($exDate == $exRangeStartDate) ||
              ($exRangeEndDate && ($exDate > $exRangeStartDate) && ($exDate <= $exRangeEndDate))
            ) {
              $skip = TRUE;
              break;
            }
          }
        }

        if ($skip) {
          unset($recursionDates[$count]);
          // lets increase the counter, so we get correct number of occurrences
          $initialCount++;
          $this->recursion->count($initialCount);
          continue;
        }

        if (isset($this->schedule['absolute_date']) && !empty($result)) {
          if ($result < new DateTime($this->schedule['absolute_date'])) {
            $initialCount++;
            $this->recursion->count($initialCount);
          }
        }
        $count++;
      }
    }
    $this->recursionDates = $recursionDates;

    return $recursionDates;
  }

  /**
   * This function copies the information from parent entity and creates other entities with same information.
   *
   * @param $templateId
   * @param array $newParams
   *   Array of all the fields & values to be copied besides the other fields.
   *
   * @return array Event.create API Result
   * @throws \CiviCRM_API3_Exception
   */
  public static function copyCreateEntity($templateId, $newParams) {
    // We need the titles from the template
    $eventTemplate = civicrm_api3('Event', 'getsingle', ['id' => $templateId, 'return' => ['template_title', 'title']]);
    $params = [
      'is_template' => 0,
      'template_title' => '',
      'parent_event_id' => NULL,
      'start_date' => $newParams['start_date'],
      'end_date' => $newParams['end_date'] ?? NULL,
      'template_id' => $templateId,
      'title' => $eventTemplate['title'] ?? $eventTemplate['template_title'],
    ];
    // Now create the event
    $newEvent = civicrm_api3('Event', 'create', $params);

    $templateParams = [
      'event_id' => $newEvent['id'],
      'template_id' => $templateId,
      'title' => $eventTemplate['template_title'],
    ];
    // Now create the entry in Event Template
    civicrm_api3('EventTemplate', 'create', $templateParams);

    return $newEvent;
  }

  /**
   * This function maps values posted from form to civicrm_action_schedule columns.
   *
   * @param array $formParams
   *   And array of form values posted .
   *
   * @return array
   */
  public function mapFormValuesToDB($formParams = []) {
    $dbParams = [];
    if (!empty($formParams['used_for'])) {
      $dbParams['used_for'] = $formParams['used_for'];
    }

    if (!empty($formParams['entity_id'])) {
      $dbParams['entity_value'] = $formParams['entity_id'];
    }

    if (!empty($formParams['repetition_start_date'])) {
      $repetitionStartDate = $formParams['repetition_start_date'];
      $repetition_start_date = new DateTime($repetitionStartDate);
      $dbParams['start_action_date'] = $repetition_start_date->format('YmdHis');
    }

    if (!empty($formParams['repetition_frequency_unit'])) {
      $dbParams['repetition_frequency_unit'] = $formParams['repetition_frequency_unit'];
    }

    if (!empty($formParams['repetition_frequency_interval'])) {
      $dbParams['repetition_frequency_interval'] = $formParams['repetition_frequency_interval'];
    }

    //For Repeats on:(weekly case)
    if ($formParams['repetition_frequency_unit'] == 'week') {
      if (!empty($formParams['start_action_condition'])) {
        $repeats_on = $formParams['start_action_condition'] ?? NULL;
        $dbParams['start_action_condition'] = implode(",", array_keys($repeats_on));
      }
    }

    //For Repeats By:(monthly case)
    if ($formParams['repetition_frequency_unit'] == 'month') {
      if ($formParams['repeats_by'] == 1) {
        if (!empty($formParams['limit_to'])) {
          $dbParams['limit_to'] = $formParams['limit_to'];
        }
      }
      if ($formParams['repeats_by'] == 2) {
        if (($formParams['entity_status_1'] ?? NULL) && ($formParams['entity_status_2'] ?? NULL)) {
          $dbParams['entity_status'] = $formParams['entity_status_1'] . " " . $formParams['entity_status_2'];
        }
      }
    }

    //For "Ends" - After:
    if ($formParams['ends'] == 1) {
      if (!empty($formParams['start_action_offset'])) {
        $dbParams['start_action_offset'] = $formParams['start_action_offset'];
      }
    }

    //For "Ends" - On:
    if ($formParams['ends'] == 2) {
      if (!empty($formParams['repeat_absolute_date'])) {
        $dbParams['absolute_date'] = $formParams['repeat_absolute_date'];
      }
    }
    return $dbParams;
  }

  /**
   * This function gets all the columns of civicrm_action_schedule table based on id(primary key)
   *
   * @param int $scheduleReminderId
   *   Primary key of civicrm_action_schedule table.
   *
   * @return object
   */
  static public function getScheduleReminderDetailsById($scheduleReminderId) {
    $query = "SELECT *
      FROM civicrm_action_schedule WHERE 1";
    if ($scheduleReminderId) {
      $query .= "
        AND id = %1";
    }
    $dao = CRM_Core_DAO::executeQuery($query,
      [
        1 => [$scheduleReminderId, 'Integer'],
      ]
    );
    $dao->fetch();
    return $dao;
  }

  /**
   * wrapper of getScheduleReminderDetailsById function.
   *
   * @param int $scheduleReminderId
   *   Primary key of civicrm_action_schedule table.
   *
   * @return array
   */
  public function getScheduleParams($scheduleReminderId) {
    $scheduleReminderDetails = [];
    if ($scheduleReminderId) {
      //Get all the details from schedule reminder table
      $scheduleReminderDetails = self::getScheduleReminderDetailsById($scheduleReminderId);
      $scheduleReminderDetails = (array) $scheduleReminderDetails;
    }
    return $scheduleReminderDetails;
  }

  /**
   * This function takes criteria saved in civicrm_action_schedule table
   * and creates recursion rule
   *
   * @param array $scheduleReminderDetails
   *   Array of repeat criteria saved in civicrm_action_schedule table .
   *
   * @return object
   *   When object
   */
  public function getRecursionFromSchedule($scheduleReminderDetails = []) {
    $r = new When();
    //If there is some data for this id
    if ($scheduleReminderDetails['repetition_frequency_unit']) {
      if ($scheduleReminderDetails['start_action_date']) {
        $currDate = date('Y-m-d H:i:s', strtotime($scheduleReminderDetails['start_action_date']));
      }
      else {
        $currDate = date("Y-m-d H:i:s");
      }
      $start = new DateTime($currDate);
      $this->recursion_start_date = $start;
      if ($scheduleReminderDetails['repetition_frequency_unit']) {
        $repetition_frequency_unit = $scheduleReminderDetails['repetition_frequency_unit'];
        if ($repetition_frequency_unit == "day") {
          $repetition_frequency_unit = "dai";
        }
        $repetition_frequency_unit = $repetition_frequency_unit . 'ly';
        $r->startDate($start)
          ->freq($repetition_frequency_unit);
      }

      if ($scheduleReminderDetails['repetition_frequency_interval']) {
        $r->interval($scheduleReminderDetails['repetition_frequency_interval']);
      }
      else {
        $r->errors[] = 'Repeats every: is a required field';
      }

      //week
      if ($scheduleReminderDetails['repetition_frequency_unit'] == 'week') {
        if ($scheduleReminderDetails['start_action_condition']) {
          $startActionCondition = $scheduleReminderDetails['start_action_condition'];
          $explodeStartActionCondition = explode(',', $startActionCondition);
          $buildRuleArray = [];
          foreach ($explodeStartActionCondition as $key => $val) {
            $buildRuleArray[] = strtoupper(substr($val, 0, 2));
          }
          $r->wkst('MO')->byday($buildRuleArray);
        }
      }

      //month
      if ($scheduleReminderDetails['repetition_frequency_unit'] == 'month') {
        if ($scheduleReminderDetails['entity_status']) {
          $startActionDate = explode(" ", $scheduleReminderDetails['entity_status']);
          switch ($startActionDate[0]) {
            case 'first':
              $startActionDate1 = 1;
              break;

            case 'second':
              $startActionDate1 = 2;
              break;

            case 'third':
              $startActionDate1 = 3;
              break;

            case 'fourth':
              $startActionDate1 = 4;
              break;

            case 'last':
              $startActionDate1 = -1;
              break;
          }
          $concatStartActionDateBits = $startActionDate1 . strtoupper(substr($startActionDate[1], 0, 2));
          $r->byday([$concatStartActionDateBits]);
        }
        elseif ($scheduleReminderDetails['limit_to']) {
          $r->bymonthday([$scheduleReminderDetails['limit_to']]);
        }
      }

      //Ends
      if ($scheduleReminderDetails['start_action_offset']) {
        if ($scheduleReminderDetails['start_action_offset'] > 30) {
          $r->errors[] = 'Occurrences should be less than or equal to 30';
        }
        $r->count($scheduleReminderDetails['start_action_offset']);
      }

      if (!empty($scheduleReminderDetails['absolute_date'])) {
        // absolute_date column of scheduled-reminder table is of type date (and not datetime)
        // and we always want the date to be included, and therefore appending 23:59
        $endDate = new DateTime($scheduleReminderDetails['absolute_date'] . ' ' . '23:59:59');
        $r->until($endDate);
      }

      if (!$scheduleReminderDetails['start_action_offset'] && !$scheduleReminderDetails['absolute_date']) {
        $r->errors[] = 'Ends: is a required field';
      }
    }
    else {
      $r->errors[] = 'Repeats: is a required field';
    }
    return $r;
  }

  /**
   * This function gets time difference between the two datetime object.
   *
   * @param DateTime $startDate
   *   Start Date.
   * @param DateTime $endDate
   *   End Date.
   *
   * @return object
   *   DateTime object which contain time difference
   */
  public static function getInterval($startDate, $endDate) {
    if ($startDate && $endDate) {
      $startDate = new DateTime($startDate);
      $endDate = new DateTime($endDate);
      if ($endDate < $startDate) {
        // If end_date is before start_date we'll just use the time part
        $endDate->setDate($startDate->format('Y'), $startDate->format('m'), $startDate->format('d'));
      }
      return $startDate->diff($endDate);
    }
  }

  /**
   * This function gets all columns from civicrm_action_schedule on the basis of event id.
   *
   * @param int $entityId
   *   Entity ID.
   * @param string $used_for
   *   Specifies for which entity type it's used for.
   *
   * @return object
   */
  public static function getReminderDetailsByEntityId($entityId, $used_for) {
    if ($entityId) {
      $query = "
        SELECT *
        FROM   civicrm_action_schedule
        WHERE  entity_value = %1";
      if ($used_for) {
        $query .= " AND used_for = %2";
      }
      $params = [
        1 => [$entityId, 'Integer'],
        2 => [$used_for, 'String'],
      ];
      $dao = CRM_Core_DAO::executeQuery($query, $params);
      $dao->fetch();
    }
    return $dao;
  }

}
