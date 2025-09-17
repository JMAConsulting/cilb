<?php

function civicrm_api3_advimport_run($params) {

  $queue = CRM_Queue_Service::singleton()->create(array(
    'type' => 'Sql',
    'name' => $params['queue'],
    'reset' => FALSE,
  ));

  $runner = new CRM_Queue_Runner(array(
    'title' => ts('CiviCRM Advanced Import'),
    'queue' => $queue,
    'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
    'onEnd' => array('CRM_Advimport_Upload_Form_Results', 'importFinished'),
  ));

  Civi::log()->info('Advimport: Running the queue...');

  $runner->runAll();
}
