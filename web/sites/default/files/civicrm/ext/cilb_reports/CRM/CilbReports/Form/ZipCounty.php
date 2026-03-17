<?php
declare(strict_types = 1);

use CRM_CilbReports_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_CilbReports_Form_ZipCounty extends CRM_Core_Form {

  protected $_id;

  protected $_zipCounty;

  public function getDefaultEntity() {
    return 'ZipCounty';
  }

  public function getDefaultEntityTable() {
    return 'civicrm_zip_county';
  }

  /**
   * Explicitly declare the form context.
   */
  public function getDefaultContext(): string {
    return 'create';
  }

  public function getEntityId() {
    return $this->_id;
  }

  /**
   * Preprocess form.
   *
   * This is called before buildForm. Any pre-processing that
   * needs to be done for buildForm should be done here.
   *
   * This is a virtual function and should be redefined if needed.
   */
  public function preProcess() {
    parent::preProcess();

    $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this);
    $this->assign('action', $this->_action);

    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE);
    CRM_Utils_System::setTitle('Add Zip County');
    if ($this->_id) {
      CRM_Utils_System::setTitle('Edit Zip County');
      $entities = civicrm_api4('ZipCounty', 'get', ['where' => [['id', '=', $this->_id]], 'limit' => 1])->first();
      $this->_zipCounty = $entities;

      $this->assign('zipCounty', $this->_zipCounty);

      $session = CRM_Core_Session::singleton();
      if ($this->_action != CRM_Core_Action::DELETE) {
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/zip-county', ['id' => $this->getEntityId(), 'action' => 'update']));
      }
      else {
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/zip-counties'));
      }
    }
    $this->addFormRule([__CLASS__, 'formRule'], $this);
  }

  public static function formRule($fields, $files, $self) {
   if (!empty($fields['zip_code']) && !empty($fields['county_id'])) {
     $result = civicrm_api4('ZipCounty', 'get', ['where' => [['zip_code', '=', $fields['zip_code']], ['county_id', '=', $fields['county_id']]], 'limit' => 1])->first();
     if (!empty($result)) {
       $errors['county_id'] = ts('Entry found with these values. Please select different zip code and county ID.');
     }
   }

   return $errors;
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {
    $this->assign('id', $this->getEntityId());
    $this->add('hidden', 'id');

    if ($this->_action != CRM_Core_Action::DELETE) {
      $elements = [
        'zip_code' => TRUE,
        'county_id' => TRUE,
      ];
      foreach ($elements as $element => $isRequired) {
        //$props = ($element == 'county_id') ? ['entity' => 'CountyCode'] : [];
        $field = $this->addField($element, [], $isRequired);
        if ($element == 'county_id') {
        //  $field = $this->
        }
        if ($this->_action == CRM_Core_Action::VIEW) {
          $field->freeze();
        }
      }

      $this->assign('elements', array_keys($elements));

      $this->addButtons([
        [
          'type' => 'upload',
          'name' => $this->_id ? E::ts('Update') : E::ts('Submit'),
          'isDefault' => TRUE,
        ],
        [
          'type' => 'cancel',
          'name' => E::ts('Cancel'),
        ],
      ]);
    }
    else {
      CRM_Utils_System::setTitle('Delete Zip County #' . $this->_zipCounty['id']);
      $this->addButtons([
        ['type' => 'submit', 'name' => E::ts('Delete'), 'isDefault' => TRUE],
        ['type' => 'cancel', 'name' => E::ts('Cancel')]
      ]);
    }

    parent::buildQuickForm();
  }

  /**
   * This virtual function is used to set the default values of various form
   * elements.
   *
   * @return array|NULL
   *   reference to the array of default values
   */
  public function setDefaultValues() {
    $defaults = $this->_zipCounty ?? [];
//print_r($defaults);
    return $defaults;
  }

  public function postProcess() {
    if ($this->_action == CRM_Core_Action::DELETE) {
      civicrm_api4('ZipCounty', 'delete', ['where' => [['id', '=', $this->_id]]]);
      CRM_Core_Session::setStatus(E::ts('Removed Zip County #' . $this->_zipCounty['id']), E::ts('Zip County'), 'success');
      return;
    }
    else {
      $params = [];
      $values = $this->controller->exportValues();
      foreach (['zip_code', 'county_id'] as $element) {
        if (!empty($values[$element])) {
          $params[$element] = $values[$element];
        }
      }
      $apiParams = ['values' => $params];
      if ($this->getEntityId()) {
        $action = 'update';
        $apiParams['where'] = [];
        $apiParams['where'][] = ['id', '=', $this->_id];
      }
      else {
        unset($values['id']);
        $action = 'create';
      }

      $zipCountyID = civicrm_api4('ZipCounty', $action, $apiParams)->first()['id'];
    }
    $this->ajaxResponse['label'] = 'Zip County ' . (($action == 'created') ? 'Added' : 'Updated');
    $this->ajaxResponse['id'] = $zipCountyID;
    CRM_Core_Session::setStatus('', E::ts($this->ajaxResponse['label']), 'success');
  }

}
