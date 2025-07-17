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
 * Class to manage the "Repeat" functionality for event
 */
class CRM_AdvancedEvents_Form_ManageEvent_Repeat extends CRM_Event_Form_ManageEvent {

  /**
   * Parent Event Start Date.
   * @var string
   */
  protected $_parentEventStartDate = NULL;

  /**
   * Parent Event End Date.
   * @var string
   */
  protected $_parentEventEndDate = NULL;

  public function preProcess() {
    parent::preProcess();
    $this->setSelectedChild('repeat');

    if (empty($this->getEventID())) {
      CRM_Core_Error::statusBounce(E::ts('You do not have permission to access this page.'));
    }

    $this->assign('currentEventId', $this->getEventID());
    $this->assign('templateId', $this->getEventID());
    $this->assign('summary', $this->get('summary'));
    $this->assign('context', 'Search');
    $this->assign("single", $this->_single);
  }

  /**
   * Set default values for the form. For edit/view mode
   * the default values are retrieved from the database
   *
   *
   * @return array
   */
  public function setDefaultValues() {
    $defaults = [];

    // Always pass current event's start date by default
    $defaults['repetition_start_date'] = $event = \Civi\Api4\Event::get(FALSE)
      ->addSelect('start_date')
      ->addWhere('id', '=', $this->getEventID())
      ->execute()
      ->first()['start_date'];
    $recurringEntityDefaults = CRM_AdvancedEvents_Form_RecurringEntity::setDefaultValues();
    return array_merge($defaults, $recurringEntityDefaults);
  }

  public function buildQuickForm() {
    CRM_AdvancedEvents_Form_RecurringEntity::buildQuickForm($this);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function postProcess() {
    if ($this->getEventID()) {
      $params = $this->controller->exportValues($this->_name);
      $event = \Civi\Api4\Event::get(FALSE)
        ->addSelect('start_date', 'end_date')
        ->addWhere('id', '=', $this->getEventID())
        ->execute()
        ->first();
      if (!empty($event['end_date'])) {
        $interval = CRM_AdvancedEvents_BAO_RecurringEntity::getInterval($event['start_date'], $event['end_date']);
        $params['intervalDateColumns'] = ['end_date' => $interval];
      }
      $params['dateColumns'] = ['start_date'];
      $params['excludeDateRangeColumns'] = ['start_date', 'end_date'];
      $params['entity_table'] = 'civicrm_event';
      $params['entity_id'] = $this->getEventID();

      // CRM-16568 - check if parent exist for the event.
      $params['parent_entity_id'] = $params['entity_id'];
      // Unset event id
      unset($params['id']);

      $url = 'civicrm/event/manage/repeat';
      $urlParams = "action=update&reset=1&id={$this->getEventID()}&selectedChild=repeat";

      $linkedEntities = [
        [
          'table' => 'civicrm_price_set_entity',
          'findCriteria' => [
            'entity_id' => $this->getEventID(),
            'entity_table' => 'civicrm_event',
          ],
          'linkedColumns' => ['entity_id'],
          'isRecurringEntityRecord' => FALSE,
        ],
        [
          'table' => 'civicrm_uf_join',
          'findCriteria' => [
            'entity_id' => $this->getEventID(),
            'entity_table' => 'civicrm_event',
          ],
          'linkedColumns' => ['entity_id'],
          'isRecurringEntityRecord' => FALSE,
        ],
        [
          'table' => 'civicrm_tell_friend',
          'findCriteria' => [
            'entity_id' => $this->getEventID(),
            'entity_table' => 'civicrm_event',
          ],
          'linkedColumns' => ['entity_id'],
          'isRecurringEntityRecord' => TRUE,
        ],
        [
          'table' => 'civicrm_pcp_block',
          'findCriteria' => [
            'entity_id' => $this->getEventID(),
            'entity_table' => 'civicrm_event',
          ],
          'linkedColumns' => ['entity_id'],
          'isRecurringEntityRecord' => TRUE,
        ],
      ];
      CRM_AdvancedEvents_Form_RecurringEntity::postProcess($params, 'civicrm_event', $linkedEntities);
      CRM_Utils_System::redirect(CRM_Utils_System::url($url, $urlParams));
    }
    else {
      CRM_Core_Error::statusBounce(E::ts('Could not find Event ID'));
    }
    parent::endPostProcess();
  }

  /**
   * This function gets the number of participant count for the list of related event ids.
   *
   * @param array $listOfRelatedEntities
   *   List of related event ids .
   *
   *
   * @return array
   */
  public static function getParticipantCountforEvent($listOfRelatedEntities = []) {
    $participantDetails = [];
    if (!empty($listOfRelatedEntities)) {
      $implodeRelatedEntities = implode(',', array_map(function ($entity) {
        return $entity['id'];
      }, $listOfRelatedEntities));
      if ($implodeRelatedEntities) {
        $query = "SELECT p.event_id as event_id,
          concat_ws(' ', e.title, concat_ws(' - ', DATE_FORMAT(e.start_date, '%b %d %Y %h:%i %p'), DATE_FORMAT(e.end_date, '%b %d %Y %h:%i %p'))) as event_data,
          count(p.id) as participant_count
          FROM civicrm_participant p, civicrm_event e
          WHERE p.event_id = e.id AND p.event_id IN ({$implodeRelatedEntities})
          GROUP BY p.event_id";
        $dao = CRM_Core_DAO::executeQuery($query);
        while ($dao->fetch()) {
          $participantDetails['countByID'][$dao->event_id] = $dao->participant_count;
          $participantDetails['countByName'][$dao->event_id][$dao->event_data] = $dao->participant_count;
        }
      }
    }
    return $participantDetails;
  }

}
