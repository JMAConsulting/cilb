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

use Civi\Api4\Event;
use Civi\Api4\Participant;
use CRM_AdvancedEvents_ExtensionUtil as E;

/**
 * This class provides the functionality to delete a group of events.
 * This class provides functionality for the actual deletion.
 */
class CRM_AdvancedEvents_Form_Task_CopyParticipants extends CRM_AdvancedEvents_Form_Task {

  /**
   * Build all the data structures needed to build the form.
   *
   * @return void
   * @throws \Exception
   */
  public function preProcess() {
    //check for edit participants
    if (!CRM_Core_Permission::checkActionPermission('CiviEvent', CRM_Core_Action::UPDATE)) {
      CRM_Core_Error::statusBounce(E::ts('You do not have permission to access this page.'));
    }
    parent::preProcess();
  }

  /**
   * Build the form object.
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function buildQuickForm() {
    $sourceEvents = Event::get(FALSE)
      ->addSelect('id', 'title', 'start_date')
      ->addWhere('id', 'IN', $this->_eventIds)
      ->addOrderBy('start_date', 'ASC')
      ->execute();

    $eventList = [];
    $eventHasParticipants = FALSE;
    foreach ($sourceEvents as $event) {
      $participantCount = Participant::get(FALSE)
        ->addWhere('event_id', '=', $event['id'])
        ->execute()
        ->count();
      if (!$eventHasParticipants && ($participantCount > 0)) {
        // We store the earliest event ID that has participants so we can pre-select it.
        $eventHasParticipants = $event['id'];
      }
      $eventList[$event['id']] = "{$event['title']} (ID: {$event['id']}) (Participants: {$participantCount}) {$event['event_start_date']}";
    }
    if (!$eventHasParticipants) {
      CRM_Core_Error::statusBounce('You need to select an event that has some participants to copy from!');
    }
    else {
      $this->_defaultSourceEvent = $eventHasParticipants;
    }
    $this->add('select', 'event_source_id', E::ts('Source Event to copy participants from: '),
      $eventList,
      TRUE
    );
    $this->addDefaultButtons(E::ts('Copy Participants'), 'done');
  }

  /**
   * Pre-select the earliest event that has participants
   *
   * @return array|NULL
   */
  public function setDefaultValues() {
    $defaults['event_source_id'] = $this->_defaultSourceEvent;
    return $defaults;
  }

  /**
   * Process the form after the input has been submitted and validated.
   *
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function postProcess() {
    $sourceEventID = $this->getSubmittedValue('event_source_id');
    if (empty($sourceEventID)) {
      CRM_Core_Error::statusBounce(E::ts('No source event found to copy participants from'));
    }

    $sourceParticipants = Participant::get(FALSE)
      ->addSelect('*', 'custom.*')
      ->addWhere('event_id', '=', $sourceEventID)
      ->execute();
    if (!$sourceParticipants->count()) {
      CRM_Core_Error::statusBounce(E::ts('The source event has no participants'));
    }

    foreach ($this->_eventIds as $eventId) {
      if ($eventId == $sourceEventID) {
        continue;
      }
      // Get existing participants for each event for duplicate check
      $existingParticipantContactIDs = Participant::get(FALSE)
        ->addSelect('contact_id')
        ->addWhere('event_id', '=', $eventId)
        ->execute()
        ->column('contact_id');
      foreach ($sourceParticipants as $participant) {
        // Check for contact already registered for event and don't add again
        if (in_array($participant['contact_id'], $existingParticipantContactIDs)) {
          continue;
        }

        // Add the participant to the event
        $fieldsToUnset = ['id', 'participant_id', 'register_date'];
        foreach ($fieldsToUnset as $field) {
          unset($participant[$field]);
        }
        $participant['event_id'] = $eventId;
        $participant['register_date'] = date('YmdHis');
        Participant::create(FALSE)
          ->setValues($participant)
          ->execute();
      }
    }
    $eventCount = count($this->_eventIds) -1;

    $status = E::ts('%1 participants added (or already existed) to %2 events.', [
      1 => $sourceParticipants->count(),
      2 => $eventCount,
    ]);

    CRM_Core_Session::setStatus($status, E::ts('Added'), 'info');
  }

}
