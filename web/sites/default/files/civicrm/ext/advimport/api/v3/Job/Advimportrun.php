<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Disable relationships that involved deleted contacts (but not permanently deleted).
 *
 * @param array $params
 *
 * @return array
 * @throws \API_Exception
 */
function civicrm_api3_job_advimportrun($params) {
  $result = null;
  $headers = $data = [];

  // @todo notify=...

  $id = NULL;
  $helper = NULL;
  $classname = NULL;

  if (!empty($params['id'])) {
    $id = $params['id'];

    // Run an advimport that was already prepared (data alreadt uploaded, but not processed)
    // @todo Add options to replay
    $classname = CRM_Core_DAO::singleValueQuery('SELECT classname FROM civicrm_advimport WHERE id = %1', [
      1 => [$id, 'Positive'],
    ]);
    $helper = new $classname();
  }
  else {
    if (empty($params['helper'])) {
      throw new Exception('helper is a required param (or id)');
    }

    $classname = $params['helper'];
    $helper = new $classname();

    try {
      // Only supports imports that do not rely on a file upload (ex: fetch from a web API)
      [$headers, $data] = $helper->getDataFromFile();
    }
    catch (Exception $e) {
      Civi::log()->info('Advimport: Failed to get data: ' . $e->getMessage());
      return;
    }

    $advimport_params = [];
    $advimport_params['filename'] = '';
    $advimport_params['contact_id'] = CRM_Core_Session::getLoggedInContactID();

    // Drush sometimes acts weird
    if (!$advimport_params['contact_id']) {
      $advimport_params['contact_id'] = 1;
    }

    // Create tag/group used for tracking entities imported/updated
    if (!empty($params['group_or_tag']) && in_array($params['group_or_tag'], ['group', 'tag'])) {
      $group_tag_label = $helper->getGroupOrTagLabel();
      $advimport_params['track_entity_type'] = $params['group_or_tag'];
      $advimport_params['track_entity_id'] = CRM_Advimport_BAO_Advimport::createGroupOrTag($params['group_or_tag'], $group_tag_label);
    }

    // Import into a temp database
    $advimport_params['table_name'] = CRM_Advimport_BAO_Advimport::saveToDatabaseTable($headers, $data);

    $api4 = \Civi\Api4\Advimport::create(FALSE)
      ->addValue('contact_id', $advimport_params['contact_id'])
      ->addValue('table_name', $advimport_params['table_name'])
      ->addValue('start_date', date('YmdHis'))
      ->addValue('classname', $classname);

    if (!empty($advimport_params['track_entity_id'])) {
      $api4->addValue('track_entity_id', $advimport_params['track_entity_id']);
    }
    if (!empty($advimport_params['track_entity_type'])) {
      $api4->addValue('track_entity_type', $advimport_params['track_entity_type']);
    }
    if ($advimport_params['filename']) {
      $api4->addValue('filename', $advimport_params['filename']);
    }
    $result = $api4->execute()->first();

    $id = $result['id'];
  }

  // @todo Code duplication from CRM_Advimport_Upload_Form_MapFields

  // Create a CiviCRM queue
  $queue_name = CRM_Advimport_Upload_Form_MapFields::QUEUE_NAME . '-' . time();

  $queue = CRM_Queue_Service::singleton()->create([
    'type' => 'Sql',
    'name' => $queue_name,
    'reset' => FALSE,
  ]);

  $count = CRM_Advimport_BAO_Advimport::processAllItems([
    'advimport_id' => $id,
    'helper' => $helper,
    'queue' => $queue,
  ]);

  $runner = new CRM_Queue_Runner([
    'title' => ts('CiviCRM Advanced Import'),
    'queue' => $queue,
    'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
    'onEnd' => ['CRM_Advimport_Upload_Form_Results', 'importFinished'],
  ]);

  Civi::log()->info("Advimport: [$classname] Running queue...");
  $runner->runAll();
  Civi::log()->info("Advimport: [$classname] Queue processing complete.");

  // Update the end_date
  CRM_Advimport_BAO_Advimport::updateStats($id);

  return civicrm_api3_create_success($result, $params, 'Job', 'advimportrun');
}


