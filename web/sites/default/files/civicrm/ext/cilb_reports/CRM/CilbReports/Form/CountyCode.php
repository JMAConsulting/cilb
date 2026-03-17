<?php
declare(strict_types = 1);

use CRM_CilbReports_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_CilbReports_Form_CountyCode extends CRM_Core_Form {

  protected $_id;

  protected $_CountyCode;

  public function getDefaultEntity() {
    return 'CountyCode';
  }

  public function getDefaultEntityTable() {
    return 'civicrm_county_code';
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
    CRM_Utils_System::setTitle('Add County Code');
    if ($this->_id) {
      CRM_Utils_System::setTitle('Edit County Code');
      $entities = civicrm_api4($this->getDefaultEntity(), 'get', ['where' => [['id', '=', $this->_id]], 'limit' => 1])->first();
      $this->_countyCode = $entities;

      $this->assign('zipCounty', $this->_countyCode);

      $session = CRM_Core_Session::singleton();
      if ($this->_action != CRM_Core_Action::DELETE) {
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/county-code', ['id' => $this->getEntityId(), 'action' => 'update']));
      }
      else {
        $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/zip-counties'));
      }
    }
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function buildQuickForm(): void {
    $this->assign('id', $this->getEntityId());
    $this->add('hidden', 'id');

    if ($this->_action != CRM_Core_Action::DELETE) {
      $elements = [
        'county' => TRUE,
        'county_code' => TRUE,
      ];
      foreach ($elements as $element => $isRequired) {
        $field = $this->addField($element, [], $isRequired);
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
      CRM_Utils_System::setTitle('Delete County Code#' . $this->_countyCode['id']);
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
    $defaults = $this->_countyCode ?? [];
    return $defaults;
  }

  public function postProcess() {
    if ($this->_action == CRM_Core_Action::DELETE) {
      civicrm_api4('CountyCode', 'delete', ['where' => [['id', '=', $this->_id]]]);
      CRM_Core_Session::setStatus(E::ts('Removed County Code #' . $this->_countyCode['id']), E::ts('County Code'), 'success');
      return;
    }
    else {
      $params = [];
      $values = $this->controller->exportValues();
      foreach (['county', 'county_code'] as $element) {
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

      $countyCodeID = civicrm_api4('CountyCode', $action, $apiParams)->first()['id'];
    }
    $this->ajaxResponse['label'] = 'County Code ' . (($action == 'created') ? 'Added' : 'Updated');
    $this->ajaxResponse['id'] = $countyCodeID;
    CRM_Core_Session::setStatus('', E::ts($this->ajaxResponse['label']), 'success');
  }

}
