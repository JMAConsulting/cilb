<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Form controller class
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Advimport_Upload_Form_Results extends CRM_Core_Form {
  protected $_mapper = NULL;

  /**
   * This function is called before buildForm. Any pre-processing that
   * needs to be done for buildForm should be done here.
   *
   * @access public
   * @return void
   */
  function preProcess() {
    parent::preProcess();

    $helperDefinition = $this->controller->get('helperDefinition');
    $this->_mapper = new $helperDefinition['class'];
  }

  /**
   * This function is used to build the form.
   *
   * @access public
   * @return void
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts("Advanced import: result"));

    $advimport_id = $this->controller->get('advimport_id');
    $stats = CRM_Advimport_BAO_Advimport::updateStats($advimport_id);

    $this->assign('import_stats', $stats);
    $this->assign('import_view_success_url', CRM_Utils_System::url('civicrm/a/#/advimport/' . $advimport_id . '/1'));
    $this->assign('import_view_warnings_url', CRM_Utils_System::url('civicrm/a/#/advimport/' . $advimport_id . '/3'));
    $this->assign('import_view_errors_url', CRM_Utils_System::url('civicrm/a/#/advimport/' . $advimport_id . '/2'));
    $this->assign('group_or_tag', $this->controller->get('group_or_tag'));

    if ($this->controller->get('group_or_tag') == 'group') {
      $this->assign('group_or_tag_id', $this->controller->get('group_or_tag_id'));

      $this->assign('group_or_tag_name', civicrm_api3('Group', 'getvalue', [
        'id' => $this->controller->get('group_or_tag_id'),
        'return' => 'title',
      ]));

      // FIXME: using Group.get return=member_count didn't work.
      $this->assign('group_or_tag_count', CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM civicrm_group_contact WHERE group_id = %1', [
        1 => [$this->controller->get('group_or_tag_id'), 'Positive'],
      ]));
    }
    elseif ($this->controller->get('group_or_tag') == 'tag') {
      $this->assign('group_or_tag_name', civicrm_api3('Tag', 'getvalue', [
        'id' => $this->controller->get('group_or_tag_id'),
        'return' => 'name',
      ]));

      // FIXME: is there an easy API way of doing this?
      $this->assign('group_or_tag_count', CRM_Core_DAO::singleValueQuery('SELECT count(*) as cpt FROM civicrm_entity_tag WHERE tag_id = %1', [
        1 => [$this->controller->get('group_or_tag_id'), 'Positive'],
      ]));
    }

    parent::buildQuickForm();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  static function importFinished(CRM_Queue_TaskContext $ctx) {
    CRM_Core_Error::debug_log_message('finished task');
  }
}
