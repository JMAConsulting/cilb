<?php

namespace Civi\Api4\Action\Job;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

class GenerateMWFReport extends AbstractAction {

  /**
   * @var bool
   */
  protected $runInNonProductionEnvironment = TRUE;

  /**
   * @var null
   */
  protected $language = NULL;

  /**
   * Report Instance ID
   * @var int
   */
  protected $instanceID;

  public function _run(Result $result) {
    \ob_start();
    $reportResult = \CRM_Report_Utils_Report::processReport([
      'instanceID' => $this->instanceID,
      'sendmail' => true,
      'output' => 'zip',
    ]);
    \ob_clean();
    if ($reportResult['is_error']) {
      throw new \CRM_Core_Exception($reportResult['messages']);
    }
    $result[] = ['messages'  => $reportResult['messages']];
    \Civi::settings()->set('cilb_reports_mwfreport_last_run_date', date('Y-m-d'));
  }

}
