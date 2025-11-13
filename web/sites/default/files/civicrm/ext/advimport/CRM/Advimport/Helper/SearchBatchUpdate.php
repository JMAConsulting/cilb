<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Helper_SearchBatchUpdate extends CRM_Contact_Form_Task {

  /**
   *
   */
  public function preProcess() {
    parent::preProcess();

    // @todo Should this call alterRow to translate data?
    // or should the implementation call it from getDataFromQuery() if necessary?
    // It probably depends on how we will implement a getoptions equivalent.
    [$headers, $data] = $this->getDataFromQuery($this->_contactIds);
    $table_name = CRM_Advimport_BAO_Advimport::saveToDatabaseTable($headers, $data);

    if ($table_name) {
      // Create a new entry in civicrm_advimport
      $result = \Civi\Api4\Advimport::create(FALSE)
        ->addValue('contact_id', CRM_Core_Session::singleton()->get('userID'))
        ->addValue('table_name', $table_name)
        ->addValue('classname', get_class($this))
        ->addValue('filename', E::ts('Batch Update'))
        ->execute()
        ->first();

      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/a/#/advimport/' . $result['id']));
    }
  }

  /**
   * This function should be overriden by the helper.
   */
  public function processItem($param) {
    throw new Exception("This function must be overriden to implement imports.");
  }

}
