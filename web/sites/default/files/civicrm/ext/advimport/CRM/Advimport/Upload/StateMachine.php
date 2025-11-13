<?php

/**
 * State machine for managing different states of the Import process.
 * Mostly based on CRM_Import_StateMachine.
 */
class CRM_Advimport_Upload_StateMachine extends CRM_Core_StateMachine {
  /**
   * Class constructor
   *
   * @param object  CRM_Advimport_Upload_Controller
   * @param int     $action
   */
  function __construct($controller, $action = CRM_Core_Action::NONE) {
    parent::__construct($controller, $action);

    $classType = str_replace('_Controller', '', get_class($controller));

    $this->_pages = array(
      $classType . '_Form_DataUpload' => NULL,
      $classType . '_Form_MapFields' => NULL,
      $classType . '_Form_Results' => NULL,
    );

    // Skip DataUpload if we are re-importing.
    // @todo Change String to Positive, when advimport_id mess is fixed
    if ($advimport_id = CRM_Utils_Request::retrieveValue('aid', 'String')) {
      $controller->set('replay_aid', $advimport_id);
      array_shift($this->_pages);

      // Default to replaying only errors, but replay_type=2 means we're re-import everything.
      $replay_type = CRM_Utils_Request::retrieveValue('replay_type', 'Positive');

      if ($replay_type == 2) {
        $controller->set('replay_type', $replay_type);
      }

      if (CRM_Utils_Request::retrieveValue('snippet', 'String') == 'json') {
        $controller->set('is_popup', true);
      }
    }
    elseif ($controller->get('replay_aid')) {
      array_shift($this->_pages);
    }

    $this->addSequentialPages($this->_pages, $action);
  }
}
