<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Advimport_Form_NewRow extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('New Row'));
    $advimport_id = CRM_Utils_Request::retrieveValue('aid', 'String', NULL, TRUE);

    // @todo Remove this when the Angularjs form is fixed
    // And we should use Api4.get instead
    if (!is_numeric($replay_aid)) {
      $advimport_id = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_advimport WHERE table_name = %1', [
        1 => ['civicrm_advimport_' . $advimport_id, 'String'],
      ]);
    }

    $dao = CRM_Core_DAO::executeQuery('SELECT * FROM civicrm_advimport WHERE id = %1', [
      1 => [$advimport_id, 'Positive'],
    ]);

    if (!$dao->fetch()) {
      throw new Exception("Could not find a valid advimport entry for $advimport_id");
    }

    // Fetch the fields (and their options)
    // This only provides interesting results if the mapping has defined fields.
    // Otherwise, we could default to adding the fields from the SQL table? (as plain text fields)
    $mapper = new $dao->classname;
    $null = NULL;
    $fields = $mapper->getMapping($null);

    foreach ($fields as $field) {
      if (!empty($field['readonly'])) {
        continue;
      }

      $options = [];

      if (method_exists($mapper, 'getOptions')) {
        $options = $mapper->getOptions($field['field']);
      }

      if (!empty($options)) {
        $this->add(
          'select',
          $field['field'],
          $field['label'],
          $options,
          $field['required'] ?? false
        );
      }
      elseif ($field['field'] == 'birth_date') {
        $this->add(
          'datepicker',
          $field['field'],
          $field['label'],
          NULL,
          $field['required'] ?? false,
          ['time' => false]
        );
      }
      else {
        $this->add(
          'text',
          $field['field'],
          $field['label'],
          NULL,
          $field['required'] ?? false
        );
      }
    }

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    // This isn't called - we handle the submit with an AngularJS callback
    $values = $this->exportValues();
    parent::postProcess();
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = [];
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
