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
    $reportResult = \CRM_Report_Utils_Report::processReport([
      'instanceID' => $this->instanceID,
      'sendmail' => true,
      'output' => 'zip',
    ]);
    if ($reportResult['is_error']) {
      throw new \Exception($result['messages']);
    }
    $result[] = $reportResult['messages'];
    \Civi::settings()->set('cilb_reports_mwfreport_last_run_date', date('Y-m-d'));
    // Remove the generated zip file from the server
    \unlink(CRM_Core_Config::singleton()->templateCompileDir . CRM_Utils_File::makeFileName('cilbChangeReport.zip'));
  }

}
