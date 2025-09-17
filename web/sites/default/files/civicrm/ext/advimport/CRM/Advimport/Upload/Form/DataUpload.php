<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Advimport_Upload_Form_DataUpload extends CRM_Core_Form {

  /**
   *
   */
  public function setDefaultValues() {
    $defaults = [];

    if ($gt = CRM_Utils_Request::retrieveValue('group_or_tag', 'String')) {
      $defaults['group_or_tag'] = $gt;
    }

    return $defaults;
  }

  /**
   * This function is used to build the form.
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts("Import Data"));

    Civi::resources()->addStyleFile('advimport', 'advimport.css');

    // Workaround redirection bug after the import queue has finished running
    if ($qfkey = CRM_Utils_Array::value('amp;qfKey', $_REQUEST)) {
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/advimport', '_qf_Results_display=true&qfKey=' . $qfkey));
    }

    // In some situations we grant users with 'access CiviCRM' access to advimport (by editing the xml menu file)
    // but not to the default core import. Maybe it's time for a custom permission?
    $has_permission = CRM_Core_Permission::check('import contacts');
    $this->assign('advimport_permission_import_contacts', $has_permission);

    if ($has_permission) {
      // NB: the file upload is optional, see the validation() function
      // This makes it possible to import data from non-file sources, such as APIs
      // or a file on disk.
      $this->add('file', 'uploadFile', E::ts('File'), ['size' =>30, 'maxlength' => 255]);

      // What kind of data is being uploaded
      $types = ['' => E::ts('- select -')] + $this->getAvailableSources();
      $this->add('select', 'source', E::ts('Source'), $types, TRUE);

      // Whether to add to a group or tag
      $options = [
        '' => E::ts('- select -'),
        'no' => E::ts('None'),
        'group' => E::ts('Group'),
        'tag' => E::ts('Tag'),
      ];

      $e = $this->add('select', 'group_or_tag', E::ts('Group or Tag'), $options, TRUE);

      if ($gt = CRM_Utils_Request::retrieveValue('group_or_tag', 'String')) {
        $e->freeze();
      }

      $this->addButtons([
        [
          'type' => 'next',
          'name' => E::ts('Next >>'),
          'isDefault' => TRUE,
        ],
      ]);
    }

    // Add a 'stop' button to interrupt an upload
    $this->flushQueueIfRequested();

    // Check the number of items currently in the queue.
    $this->addQueueStatus();

    // Show recent imports
    $this->addRecentImports();

    // Export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Data validation after submit.
   */
  public function validate() {
    $valid = TRUE;
    $values = $this->exportValues();

    // Ask the mapping to validate the user's selection.
    $helperDefinition = $this->getHelperForSource($values['source']);
    $helper = new $helperDefinition['class']($helperDefinition);

    if (method_exists($helper, 'validateUploadForm')) {
      $valid = $helper->validateUploadForm($this);
    }
    else {
      // Legacy behaviour
      $e = $this->controller->_pages['DataUpload']->getElement('uploadFile');
      $file = $e->getValue();

      if (empty($file['name'])) {
        $this->setElementError('uploadFile', E::ts("Please select a file to upload."));
        $valid = false;
      }
    }

    return $valid;
  }

  /**
   * This function is called after the form is validated. Any
   * processing of form state etc should be done in this function.
   * Typically all processing associated with a form should be done
   * here and relevant state should be stored in the session
   */
  public function postProcess() {
    $values = $this->controller->exportValues();
    $helperDefinition = $this->getHelperForSource($values['source']);

    $this->controller->set('is_error', false);
    $this->controller->set('helperDefinition', $helperDefinition);
    // @todo mapper variable is deprecated, use helperDefinition instead
    $this->controller->set('mapper', $helperDefinition['class']);
    $this->controller->set('group_or_tag', $values['group_or_tag']);

    $e = $this->controller->_pages['DataUpload']->getElement('uploadFile');
    $file = $e->getValue();
    $tmp_file = null;
    $orig_name = '';

    if (!empty($file['name'])) {
      $tmp_file = $file['tmp_name'];
      $orig_name = $file['name'];

      // Save the file for later reference
      $saved_file_id = $this->copyFile($file);
      $this->controller->set('saved_file_id', $saved_file_id);
    }

    $helper = new $helperDefinition['class']($helperDefinition);

    try {
      [$headers, $data] = $helper->getDataFromFile($tmp_file);
    }
    catch (Exception $e) {
      $this->controller->set('is_error', $e->getMessage());
      parent::postProcess();
      return;
    }

    // Set the tag/group name, if one is to be created
    if (in_array($values['group_or_tag'], ['group', 'tag'])) {
      $label = $helper->getGroupOrTagLabel([
        'filename' => $orig_name,
      ]);
      $group_or_tag_id = CRM_Advimport_BAO_Advimport::createGroupOrTag($values['group_or_tag'], $label);
      $this->controller->set('group_or_tag_id', $group_or_tag_id);
    }

    // Import into a temp DB table
    $table_name = CRM_Advimport_BAO_Advimport::saveToDatabaseTable($headers, $data, $this->controller);

    if ($table_name) {
      // Create a new entry in civicrm_advimport
      $result = \Civi\Api4\Advimport::create(FALSE)
        ->addValue('contact_id', CRM_Core_Session::singleton()->get('userID'))
        ->addValue('classname', $helperDefinition['class'])
        ->addValue('table_name', $table_name)
        ->addValue('filename', $orig_name)
        ->addValue('track_entity_type', $values['group_or_tag'])
        ->addValue('track_entity_id', $this->controller->get('group_or_tag_id'))
        ->execute()
        ->first();

      $this->controller->set('advimport_id', $result['id']);
    }

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
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * Adds the 'campaign' field, based on the label and options
   * of the 'campaign' custom field in CiviCRM.
   * NB: this field has a multi-lingual label/options.
   */
  public function addWidgetCampaign() {
    global $tsLocale;

    // Label
    $field_label = '';
    $result = civicrm_api3('CustomField', 'getsingle', [
      'id' => 58,
      'is_active' => 1,
      'option.language' => $tsLocale,
    ]);

    $field_label = $result['label'];
    $field_options = CRM_Advimport_PseudoConstant::getCampaigns(TRUE);

    // Add the field to the form
    $this->add('select', 'campaign', $field_label, $field_options);
  }

  /**
   * Returns an array with a list of years from 'now' to +10 years.
   *
   * @return array of years.
   */
  public function getOptionYears() {
    $years = ['' => E::ts('- year -')];

    $y = date('Y');

    for ($i = 0; $i < 10; $i++) {
      $years[$y - $i] = $y - $i;
    }

    return $years;
  }

  /**
   * If a 'request' parameter has been provided with, ex: ?flush=[queue-name],
   * it will reset that queue (ex: to interrupt an import with errors).
   */
  public function flushQueueIfRequested() {
    $flush = CRM_Utils_Request::retrieve('flush', 'String', $this);

    if (! $flush) {
      return;
    }

    $requiredPrefix = CRM_Advimport_Upload_Form_MapFields::QUEUE_NAME . '-';
    if (strpos($flush, $requiredPrefix) !== 0) {
      // Don't flush somebody else's queue!
      return;
    }

    $queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => $flush,
      'reset' => FALSE,
    ]);

    if ($cpt = $queue->numberOfItems()) {
      $queue->deleteQueue();
      CRM_Core_Session::setStatus(E::ts('The task %1 with %2 items has been cancelled.', [1 => $flush, 2 => $cpt]));
    }
  }

  /**
   * Checks for active queues, and assigns the status to smarty variables.
   */
  public function addQueueStatus() {
    $active_queues = [];
    $dao = CRM_Core_DAO::executeQuery('SELECT DISTINCT(queue_name), submit_time, count(*) as cpt FROM civicrm_queue_item WHERE queue_name LIKE %1 GROUP BY queue_name, submit_time', [
      1 => [CRM_Advimport_Upload_Form_MapFields::QUEUE_NAME . '-%', 'String'],
    ]);

    while ($dao->fetch()) {
      $active_queues[] = [
        'queue_name' => $dao->queue_name,
        'submit_time' => $dao->submit_time,
        'items' => $dao->cpt,
      ];
    }

    $this->assign('active_queues', $active_queues);
    $this->assign('active_queues_count', count($active_queues));
  }

  /**
   * Shows recent imports
   */
  public function addRecentImports() {
    $imports = [];

    $api4 = \Civi\Api4\Advimport::get(false)
      ->addOrderBy('id', 'DESC')
      ->setLimit(15);

    // In some projects, we allow users without "import contacts" to access advimport
    // either to run batch updates, or custom imports. For now we patch the menu xml.
    // If they stumble on the Advimport screen, then they should not see imports by other users,
    // since that might let them see data on users they do not normally have access to (if ACLs).
    // @todo This should be part of the permission checks in the 'get' call
    // and we could remove the 'skip_permissions' from the above (false)
    if (!(CRM_Core_Permission::check('administer CiviCRM data') || CRM_Core_Permission::check('administer CiviCRM'))) {
      $userID = CRM_Core_Session::getLoggedInContactID();
      $api4->addWhere('contact_id', '=', $userID);
    }

    $imports = $api4->execute();

    foreach ($imports as &$import) {
      // Provide a short name for the link to the 'view errors' page
      $import['short_table_name'] = preg_replace('/^civicrm_advimport_/', '', $import['table_name']);

      // Lookup the name of the contact
      $import['contact_display_name'] = civicrm_api3('Contact', 'getvalue', [
        'id' => $import['contact_id'],
        'return' => 'display_name',
      ]);

      // If the import is not finished, the stats will not have been updated yet
      // The table might have been deleted
      // Normally we should set an End Date when the table is deleted.
      if ((empty($import['end_date']) || $import['end_date'] == '0000-00-00 00:00:00') && !empty($import['table_name']) && CRM_Core_DAO::checkTableExists($import['table_name'])) {
        // @todo The check against 0000-00-00 is obviously because of a SQL schema bug
        $import['end_date'] = '';
        $stats = CRM_Advimport_BAO_Advimport::updateStats($import['id'], FALSE);
        $import = array_merge($import, $stats);
      }
    }

    $this->assign('recent_imports', $imports);
  }

  /**
   * @deprecated
   */
  public function getMappings() {
    CRM_Core_Error::deprecatedFunctionWarning('CRM_Advimport_Upload_Form_DataUpload::getMappings is deprecated, replaced by CRM_Advimport_BAO_Advimport::getHelpers');
    return CRM_Advimport_BAO_Advimport::getHelpers();
  }

  /**
   * Returns an array of available import sources.
   * Mostly alone to be more consistant with getMappingForSource().
   */
  public function getAvailableSources() {
    $map = CRM_Advimport_BAO_Advimport::getHelpers();

    $types = [
      0 => E::ts('- select -'),
    ];

    foreach ($map as $key => $val) {
      if (!empty($map['hidden'])) {
        continue;
      }
      $types[$key] = $val['label'];
    }

    return $types;
  }

  /**
   * Returns the helper info for a given source.
   */
  public function getHelperForSource($id) {
    $map = CRM_Advimport_BAO_Advimport::getHelpers();

    if (empty($map[$id])) {
      throw new Exception('Invalid id: ' . $id);
    }

    return $map[$id];
  }

  /**
   * Extract the column headers (first row).
   * NB: we're not using all of $data[1], because it contains values from column 'A' to 'AMK' (1024+1 cols).
   *
   * @param array &$data PHPExcel data
   * @returns array key/val.
   */
  public function extractColumnHeaders(&$data) {
    $headers = [];

    foreach ($data[1] as $key => $val) {
      if ($val) {
        $headers[$key] = trim($val);
      }
    }

    return $headers;
  }

  /**
   * Save an uploaded file, for later reference.
   *
   * @param array $file object.
   *
   * @return int
   * @throws \CiviCRM_API3_Exception
   */
  public function copyFile($file) {
    $config = CRM_Core_Config::singleton();
    $makefile = CRM_Utils_File::makeFileName($file['name']);

    $dstdir = $config->customFileUploadDir . '/advimport';
    $dstfile = $dstdir . '/' . $makefile;

    if (!file_exists($dstdir)) {
      mkdir($dstdir);
    }

    $ret = copy($file['tmp_name'], $dstfile);

    if (! $ret) {
      CRM_Core_Session::setStatus(E::ts("Erreur de sauvegarde du fichier téléversé. Vérifier les permissions sur le système de fichiers du serveur: %1.", [1 => $dstfile]), 'warning');
    }

    // Get the name of the user uploading
    $session = CRM_Core_Session::singleton();
    $contact_id = $session->get('userID');
    $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contact_id, 'return.display_name' => 1]);

    // Save an entry in civicrm_file.
    $fileDAO = new CRM_Core_DAO_File();
    $fileDAO->uri = 'advimport/' . $makefile;
    $fileDAO->mime_type = $file['type'];
    $fileDAO->file_type_id = NULL;
    $fileDAO->upload_date = date('YmdHis');
    $fileDAO->description = 'File upload by ' . $contact['display_name'] . ' (' . $contact_id . ')';
    $fileDAO->save();

    // return the file ID
    return $fileDAO->id;
  }

}
