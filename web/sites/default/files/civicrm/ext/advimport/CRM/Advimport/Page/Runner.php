<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Page_Runner extends CRM_Core_Page {

  public function run() {
    $qrid = CRM_Utils_Request::retrieveValue('qrid', 'String', TRUE);

    // CiviCRM only allows to continue running queues started by the same user.
    // This check comes from CRM/Queue/Runner.php
    // but we can create a new queue this way and then continue processing.
    $queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => $qrid,
      'reset' => FALSE,
    ]);

    $runner = new CRM_Queue_Runner([
      'title' => ts('CiviCRM Advanced Import'),
      'queue' => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
      'onEnd' => array('CRM_Advimport_Upload_Form_Results', 'importFinished'),
    ]);

    // does not return
    $runner->runAllViaWeb();
  }

}
