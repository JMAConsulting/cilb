<?php

use CRM_CilbExamRegistration_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_CilbExamRegistration_Form_Transfer extends CRM_Core_Form {
 
  protected $_pid;
  protected $oldEventId;

  public function preProcess() {
    $this->_pid = CRM_Utils_Request::retrieve('pid', 'Positive', $this);
    $participant = civicrm_api3('Participant', 'getsingle', ['id' => $this->_pid]);
    $this->oldEventId = $participant['event_id'];
    $this->assign('participant_id', $this->_pid);
    $this->assign('old_event_id', $this->oldEventId);
    if ($this->oldEventId) {
      $oldEvent = \Civi\Api4\Event::get(FALSE)
        ->addWhere('id', '=', $this->oldEventId)
        ->addSelect('title')
        ->execute()->first();
      $this->assign('old_event_title', $oldEvent['title'] ?? '');

      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('id', '=', $participant['contact_id'])
        ->addSelect('display_name')
        ->execute()->first();
      $this->assign('contact_name', $contact['display_name'] ?? '');
    }
    parent::preProcess();
  }

  public function buildQuickForm() {
    $eligibleEvents = \Civi\Api4\Event::get(FALSE)
      ->addSelect('id', 'title', 'start_date')
      ->addWhere('event_type_id:name', '=', 'Plumbing')
      ->addWhere('is_active', '=', 1)
      ->addWhere('Exam_Details.Exam_Part', '=', 'TK') 
      ->addWhere('id', '!=', $this->oldEventId)
      ->setLimit(0)
      ->execute()
      ->indexBy('id');

    $eventOptions = ['' => E::ts('- Select Target Event -')];
    foreach ($eligibleEvents as $event) {
      $eventOptions[$event['id']] = "{$event['title']} ({$event['start_date']})";
    }

    $this->add('select', 'new_event_id', E::ts('Transfer To Exam'), $eventOptions, TRUE, ['class' => 'crm-select2']);
    $this->addButtons([
        [
          'type' => 'submit',
          'name' => E::ts('Transfer Exam Registration'),
          'isDefault' => TRUE,
        ],
      ]
    );


    $this->addFormRule(['CRM_CilbExamRegistration_Form_Transfer', 'formRule']);

    parent::buildQuickForm();
  }

  public static function formRule($values) {
    $errors = [];
    if (empty($values['new_event_id'])) {
      $errors['new_event_id'] = E::ts('Select a Plumbing Exam.');
    }
    return $errors;
  }

  public function postProcess() {
    $params = $this->controller->exportValues($this->_name);
    $newEventId = $params['new_event_id'];

    \Civi\Api4\Participant::update(FALSE)
      ->addWhere('id', '=', $this->_pid)
      ->addValue('event_id', $newEventId)
      ->execute();

    $participant = \Civi\Api4\Participant::get(FALSE)
      ->addWhere('id', '=', $this->_pid)
      ->addSelect('contact_id')
      ->execute()
      ->first();

    \Civi\Api4\Activity::create(FALSE)
      ->addValue('source_contact_id', CRM_Core_Session::getLoggedInContactID())
      ->addValue('target_contact_id', $participant['contact_id'])
      ->addValue('source_record_id', $this->_pid)
      ->addValue('activity_type_id:name', 'Change Exam Registration')
      ->addValue('subject', "Transferred from Exam ID {$this->oldEventId} to Exam ID {$newEventId}")
      ->addValue('status_id:name', 'Completed')
      ->execute();

    CRM_Core_Session::setStatus(E::ts('Registration transferred successfully.'), E::ts('Success'), 'success');
  }

}
