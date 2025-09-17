<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Form controller class
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Advimport_Upload_Form_MapFields extends CRM_Core_Form {
  protected $_mapper = NULL;
  const QUEUE_NAME = 'advimport';

  /**
   * This function is called before buildForm. Any pre-processing that
   * needs to be done for buildForm should be done here.
   *
   * @access public
   * @return void
   */
  function preProcess() {
    parent::preProcess();

    $this->reloadPreviousImport();
    $helperDefinition = $this->controller->get('helperDefinition');
    $this->_mapper = new $helperDefinition['class']($helperDefinition);

    // Check if the user tried to go back after finishing the upload.
    // Resend back to the first step.
    if (!$this->controller->get('tableName')) {
      $this->controller->resetPage('DataUpload');
    }
  }

  /**
   * Implements setDefaultValues() for QuickForm.
   * Sets the fields associations if a label match was found.
   */
  function setDefaultValues() {
    $defaults = [];

    $headers = $this->controller->get('headers') ?? [];
    $fields = $this->_mapper->getMapping($this);

    // If we are reloading an import, use the saved values as defaults
    $replay_aid = $this->controller->get('replay_aid');

    if ($replay_aid) {
      $mapping = $this->controller->get('mapping');
      if (!empty($mapping)) {
        $defaults += $mapping;
      }
    }

    // If reloading the form, fetch the user-submitted values
    $mapping = $this->exportValues();

    // For each column of the file uploaded, we try to match to known
    // fields from our data mapping. We also try known aliases.
    // Checks are case-insensitive.
    foreach ($headers as $label) {
      $header_id = CRM_Advimport_Utils::convertToMachineName($label);

      // Default was already set by reload
      if (!empty($defaults[$header_id])) {
        continue;
      }

      // Mapping user-selected
      if (!empty($mapping[$header_id])) {
        $defaults[$header_id] = $mapping[$header_id];
        continue;
      }

      // Try to guess some defaults
      foreach ($fields as $field) {
        // Check for a match on the main field label.
        $field_id = CRM_Advimport_Utils::convertToMachineName($field['label']);

        if ($header_id == $field_id) {
          $defaults[$header_id] = $field['field'];
        }
        elseif ($header_id == 'col_' . $field_id) {
          $defaults[$header_id] = $field['field'];
        }
        else {
          // Check for a match on the field aliases.
          if (!empty($field['aliases'])) {
            foreach ($field['aliases'] as $alias) {
              $alias_id = CRM_Advimport_Utils::convertToMachineName($alias);

              if ($alias_id == $header_id) {
                $defaults[$header_id] = $field['field'];
              }
            }
          }
        }
      }
    }

    // Allow helpers to override
    if (method_exists($this->_mapper, 'mapfieldsSetDefaultValues')) {
      $defaults += $this->_mapper->mapfieldsSetDefaultValues($this);
    }

    return $defaults;
  }

  /**
   * Prepare to replay a previous import.
   */
  function reloadPreviousImport() {
    $replay_aid = $this->controller->get('replay_aid');
    $replay_type = $this->controller->get('replay_type');

    if (!$replay_aid) {
      return;
    }

    // @todo Remove this when the 'id' mess is fixed on the Angular forms
    if (!is_numeric($replay_aid)) {
      $replay_aid = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_advimport WHERE table_name = %1', [
        1 => ['civicrm_advimport_' . $replay_aid, 'String'],
      ]);
    }

    $dao = CRM_Core_DAO::executeQuery('SELECT * FROM civicrm_advimport WHERE id = %1', [
      1 => [$replay_aid, 'Positive'],
    ]);

    if (!$dao->fetch()) {
      throw new Exception("Could not find a valid advimport entry for $replay_aid");
    }

    // @todo Deprecated
    $this->controller->set('mapper', $dao->classname);

    // Workaround because we do not have the helperDefinition so we return the first one matching
    $helpers = CRM_Advimport_BAO_Advimport::getHelpers();

    // In case the helper definition does not exist, set a basic one (ex: LookupTableContactProcessor)
    $this->controller->set('helperDefinition', [
      'class' => $dao->classname,
      'label' => 'Temporary placeholder',
    ]);

    foreach ($helpers as $h) {
      if ($dao->classname == $h['class']) {
        $this->controller->set('helperDefinition', $h);
        break;
      }
    }

    // Load the headers from the previous import
    $this->controller->set('advimport_id', $replay_aid);
    $this->controller->set('group_or_tag', $dao->track_entity_type);
    $this->controller->set('group_or_tag_id', $dao->track_entity_id);
    $this->controller->set('tableName', $dao->table_name);

    $mapping = json_decode($dao->mapping, TRUE);
    $headers = !empty($mapping) ? array_keys($mapping) : [];
    $this->controller->set('headers', $headers);

    // This is used by CRM_Advimport_Advimport_APIv3::mapfieldsSetDefaultValues()
    $this->controller->set('mapping', $mapping); // FIXME: creates confusion with getMapping()

    // This will be used to reset the queue before importing
    // Don't do it now, the user might change their mind.
    $this->controller->set('reloading_import', TRUE);

    if ($replay_type == 2) {
      CRM_Core_Session::setStatus(E::ts("A previous import will be re-imported. This can be useful when testing an import. However, depending on your configuration, it can create duplicate data."), E::ts('Re-import'), 'warning', ['expires' => 0]);
    }
  }

  /**
   * This function is used to build the form.
   *
   * @access public
   * @return void
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts("Advanced import: Map Fields"));

    if ($error = $this->controller->get('is_error')) {
      $this->assign('error', $error);

      $this->addButtons([
        [
          'type' => 'back',
          'name' => E::ts('Previous'),
          'isDefault' => TRUE,
        ],
      ]);

      $this->assign('elementNames', $this->getRenderableElementNames());
      parent::buildQuickForm();

      return;
    }

    $fields = $this->_mapper->getMapping($this);
    $this->assign('fields', $fields);
    $this->assign('activity_subject', $this->_mapper->getHelperLabel());

    // For each header, show a <select> to map to the database field.
    $headers = $this->controller->get('headers');
    $options = $this->fieldsToOptions($fields);

    $helperDefinition = $this->controller->get('helperDefinition');
    $helper = new $helperDefinition['class']($helperDefinition);
    $mapfield_method = 'user-select';

    if (method_exists($helper, 'mapfieldMethod')) {
      $mapfield_method = $helper->mapfieldMethod();
    }

    $this->assign('mapfield_method', $mapfield_method);

    // Ex: called from a popup from the review screen, be a bit more user-friendly
    $is_popup = $this->controller->get('is_popup');
    $this->assign('is_popup', $is_popup);

    if ($is_popup) {
      CRM_Utils_System::setTitle(E::ts("Import"));
    }

    if (method_exists($helper, 'mapfieldsBuildFormPre')) {
      $helper->mapfieldsBuildFormPre($this);
    }

    if (!empty($fields)) {
      foreach ($headers as $label) {
        $field_id = CRM_Advimport_Utils::convertToMachineName($label);

        if ($mapfield_method == 'skip') {
          $this->addElement('hidden', 'col_' . $field_id, $label);
        }
        else {
          // mapfieldsBuildFormPre might have already added the field
          if (!$this->elementExists($field_id)) {
            $this->addElement('select', $field_id, $label, $options);
          }
        }
      }
    }

    $this->addButtons([
      [
        'type' => 'back',
        'name' => E::ts('Previous'),
      ],
      [
        'type' => 'done',
        'name' => E::ts('Review Data >>'),
        'isDefault' => FALSE,
      ],
      [
        'type' => 'next',
        'name' => E::ts('Start Import >>'),
        'isDefault' => TRUE,
      ],
    ]);

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * This function is called after the form is validated. Any
   * processing of form state etc should be done in this function.
   * Typically all processing associated with a form should be done
   * here and relevant state should be stored in the session
   *
   * @access public
   * @return void
   */
  function postProcess() {
    $advimport_id = $this->controller->get('advimport_id');

    // This is to let the helper decide whether we need to ask for more
    // information before starting the import.
    $helperDefinition = $this->controller->get('helperDefinition');
    $helper = new $helperDefinition['class']($helperDefinition);

    if (method_exists($helper, 'mapfieldsPostProcessPre')) {
      // If it returns false, then we reload the page
      if (!$helper->mapfieldsPostProcessPre($this)) {
        // For some reason, resetPage did not work and we would end up redirected to home.
        $qfkey = $this->controller->_key;
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/advimport', '_qf_MapFields_display=true&qfKey=' . $qfkey));
        return;
      }
    }

    // Fetch the user-submitted column mapping
    $values = $this->exportValues();

    // remove QF-specific keys
    unset($values['qfKey']);
    unset($values['_qf_MapFields_next']);
    unset($values['_qf_MapFields_done']);
    unset($values['_qf_default']);
    unset($values['entryURL']);

    // Reset the error status, if re-importing
    if ($this->controller->get('reloading_import')) {
      $import_table_name = $this->controller->get('tableName');
      $replay_type = $this->controller->get('replay_type');

      CRM_Logging_Schema::disableLoggingForThisConnection();

      if ($replay_type == 2) {
        CRM_Core_DAO::executeQuery('UPDATE ' . $import_table_name . ' SET import_status = 0, import_error = NULL');
      }
      else {
        CRM_Core_DAO::executeQuery('UPDATE ' . $import_table_name . ' SET import_status = 0, import_error = NULL WHERE import_status = 2');
      }

      CRM_Advimport_BAO_Advimport::reEnableLogging();
    }

    // TODO: validate if required fields were set?
    // Call a function from the helper, so that helpers can extend a base class
    // and override.

    // Save the field mappings
    // We ignore if running in a popup, and if the mapping is empty (ex: core uploads, for now)
    if (!$this->controller->get('is_popup') && !empty($values)) {
      // Get the existing mapping data, and merge the form data into it
      // For example, in the Core Contact import, we do not expose all mapping options
      $mapping = CRM_Core_DAO::singleValueQuery('SELECT mapping FROM civicrm_advimport WHERE id = %1', [
        1 => [$advimport_id, 'Positive'],
      ]);

      if ($mapping) {
        $mapping = json_decode($mapping, TRUE);
        $mapping = array_merge($mapping, $values);
      }
      else {
        $mapping = $values;
      }

      $mapping = json_encode($mapping);

      CRM_Core_DAO::executeQuery('UPDATE civicrm_advimport SET mapping = %1 WHERE id = %2', [
        1 => [$mapping, 'String'],
        2 => [$advimport_id, 'Positive'],
      ]);
    }

    // Check if the 'Review Data' button was clicked
    $buttonUsed = $this->controller->getButtonName();

    if ($buttonUsed === '_qf_MapFields_done') {
      // Review data button.
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/a/#/advimport/' . $advimport_id));
    }

    // Create a CiviCRM queue
    $queue_name = self::QUEUE_NAME . '-' . time();

    $queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => $queue_name,
      'reset' => FALSE,
    ]);

    $params = [];

    $count = CRM_Advimport_BAO_Advimport::processAllItems([
      'advimport_id' => $advimport_id,
      // $helper would work too, but I suppose the _mapper might have user input too?
      'helper' => $this->_mapper,
      'queue' => $queue,
    ]);

    // Avoid "could not claim next task" errors if there are no items to process
    if ($count) {
      $runner = new CRM_Queue_Runner([
        'title' => E::ts('CiviCRM Advanced Import'),
        'queue' => $queue,
        'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
        'onEnd' => ['CRM_Advimport_Upload_Form_Results', 'importFinished'],
        // url() params required for WordPress, similar to:
        // https://github.com/systopia/de.systopia.civioffice/pull/68/files
        'onEndUrl' => CRM_Utils_System::url('civicrm/advimport', ['_qf_Results_display' => 'true', 'qfKey' => $_REQUEST['qfKey']], FALSE, NULL, FALSE),
      ]);

      // does not return
      $runner->runAllViaWeb();
    }
    else {
      CRM_Core_Session::setStatus(E::ts('All items were already processed. To force the import to run again, go to the main Advanced Import screen and click the re-import button.'), '', 'warning');
    }

    parent::postProcess();
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

  function fieldsToOptions($fields, $default_empty = TRUE) {
    $options = [];

    if ($default_empty) {
      $options[0] = E::ts('- select -');
    }

    foreach ($fields as $key => $val) {
      $options[$key] = $val['label'];
    }

    return $options;
  }

  /**
   * Callback for the queue runner. This does the actual import.
   */
  static function processItem(CRM_Queue_TaskContext $ctx, $batch, $message = NULL) {
    static $mapping = NULL;
    static $helper = NULL;

    foreach ($batch as $params) {
      // Fetch the field mappings
      // Not very efficient, but it's done only once per batch, so should be marginal cost.
      if (!isset($mapping)) {
        $dao = CRM_Core_DAO::executeQuery('SELECT mapping, classname FROM civicrm_advimport WHERE id = %1', [
          1 => [$params['advimport_id'], 'Positive'],
        ]);

        if (!$dao->fetch()) {
          throw new Exception("processItem: failed to fetch info about import ID: {$params['advimport_id']}");
        }

        $mapping = $dao->mapping;
        $classname = $dao->classname;

        if (!empty($mapping)) {
          $mapping = json_decode($mapping, TRUE);
        }

        // Set the mapper helper class
        $helper = new $classname();
      }

      // Fetch the data from the advimport_xx temp table
      // I'm not sure what was the intent here. If we didn't want to import
      // a record already imported, or an error, then why is it in the batch?
      // Pretty sure we can just cleanup/simplify a lot of this code.
      $dao = CRM_Core_DAO::executeQuery("SELECT * FROM %1 where `row`= %2 AND import_status != 1", [
        1 => [$params['import_table_name'], 'MysqlColumnNameOrAlias'],
        2 => [$params['import_row_id'], 'Positive'],
      ]);

      if ($dao->fetch()) {
        if ($dao->import_status == 1) {
          // FIXME: This contradicts above SQL condition which excludes import_status=1
          // User might have reloaded the browser page, and queue is running from a previous state
          $ctx->log->log('Import: skipping ' . $params['import_table_name'] . ', row ' . $params['import_row_id'] . ' (assuming browser reload)');
          continue;
        }

        if ($dao->import_status == 2 || $dao->import_status == 3) {
          // We are probably re-trying, skip the error/warning
          // (there are probably other valid items in the batch, so we don't want to skip completely)
          $ctx->log->log('Import: skipping ' . $params['import_table_name'] . ', row ' . $params['import_row_id'] . ' (assuming retry)');
          continue;
        }

        $data = self::convertDaoToArray($dao);
        $params += $data;
      }
      else {
        // throw new Exception("processItem: row not found or already processed: " . $params['import_table_name'] . "." . $params['import_row_id']);
        // There might be other items in this batch (ex: when re-importing errors)
        continue;
      }

      // Data is stored in the tmp table with their original column mames
      // Here is where we remap those fields to the new field names.
      if (!empty($mapping)) {
        foreach ($params as $key => $val) {
          // QuickForm replaces the spaces by underscores when it generates the mapfields form
          // so the mapping saved in DB will have underscores.
          // FIXME: This might not be necessary anymore, since we convert to machine key.
          $key = CRM_Advimport_Utils::convertToMachineName($key);

          if (isset($mapping[$key])) {
            $new_key = $mapping[$key];

            // If the CSV column key is the same as the mapping key
            // we want to avoid doing an 'unset' on the params :-)
	    if ($new_key != $key) {
              $params[$new_key] = $params[$key];
              unset($params[$key]);
            }
          }
        }
      }

      // NB: this might throw an exception, but depending on the errorMode
      // provided to the Queue Runner, it will either prompt or ignore.
      try {
        $helper->processItem($params);

        // NB: ignore the update if the import triggered warnings (we won't retry it anyway).
        // Core import already updates the rows, so only update the status of non-core imports
        CRM_Logging_Schema::disableLoggingForThisConnection();
        CRM_Core_DAO::executeQuery('UPDATE ' . $params['import_table_name'] . ' SET import_status = 1 where `row`= %1 and import_status <> 3', [
          1 => [$params['import_row_id'], 'Positive'],
        ]);
        CRM_Advimport_BAO_Advimport::reEnableLogging();
      }
      catch (Exception $e) {
        // Log and throw back for errorMode handling.
        $ctx->log->log('Import: ' . $e->getMessage() . ' --- ' . print_r($params, 1));

        CRM_Logging_Schema::disableLoggingForThisConnection();
        CRM_Core_DAO::executeQuery('UPDATE ' . $params['import_table_name'] . ' SET import_status = 2, import_error = %2 where `row`= %1', [
          1 => [$params['import_row_id'], 'Positive'],
          2 => [$e->getMessage(), 'String'],
        ]);
        CRM_Advimport_BAO_Advimport::reEnableLogging();
      }
    }

    return TRUE;
  }

  /**
   * Callback for the queue runner post-import task.
   *
   * Note that unlike processItem, this doesn't offer much in terms of
   * logging, or re-trying if there is an error, because no state is saved.
   * (it would be nice to have)
   */
  static function postImport(CRM_Queue_TaskContext $ctx, $params, $message = NULL) {
    $dao = CRM_Core_DAO::executeQuery('SELECT classname FROM civicrm_advimport WHERE id = %1', [
      1 => [$params['advimport_id'], 'Positive'],
    ]);

    if (!$dao->fetch()) {
      throw new Exception("processItem: failed to fetch info about import ID: {$params['advimport_id']}");
    }

    // Set the mapper helper class
    $classname = $dao->classname;
    $helper = new $classname();

    // NB: this might throw an exception, but depending on the errorMode
    // provided to the Queue Runner, it will either prompt or ignore.
    try {
      $helper->postImport($params);
    }
    catch (Exception $e) {
      // Log and throw back for errorMode handling.
      $ctx->log->log('Import: ' . $e->getMessage() . ' --- ' . print_r($params, 1));
    }

    return TRUE;
  }

  /**
   * Given a DAO object, returns an array, filtering out internal keys.
   */
  static public function convertDaoToArray($dao, $remove_import_info = TRUE) {
    // Use this instead of direct casting to avoid private properties.
    $data = get_object_vars($dao);

    unset($data['N']);

    if ($remove_import_info) {
      unset($data['row']);
      unset($data['import_status']);
      unset($data['import_error']);
    }

    foreach ($data as $key => $val) {
      if (substr($key, 0, 1) == '_') {
        unset($data[$key]);
      }
    }

    return $data;
  }
}
