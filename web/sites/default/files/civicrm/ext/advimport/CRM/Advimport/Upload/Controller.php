<?php

/**
 * Controller for the multi-form process to import data.
 * Mostly based off CRM_Import_Controller/CRM_Contact_Import_Controller.
 */
class CRM_Advimport_Upload_Controller extends CRM_Core_Controller {

  /**
   * Constructor - handle the state machine.
   */
  function __construct($title = NULL, $action = CRM_Core_Action::NONE, $modal = TRUE) {
    parent::__construct($title, $modal);

    $this->_stateMachine = new CRM_Advimport_Upload_StateMachine($this, $action);

    // Create and instantiate the pages
    $this->addPages($this->_stateMachine, $action);

    // Add all the actions
    $this->addActions();
  }

}
