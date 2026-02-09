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
   * CBT Report Instance ID
   * @var int
   */
  protected $cbTInstanceID;

  /**
   * Paper Report Instance ID
   * @var int
   */
  protected $paperInstanceID;

  public function _run(Result $result) {
    \ob_start();
    $reportResult = \CRM_Report_Utils_Report::processReport([
      'instanceId' => $this->cbTInstanceID,
      'sendmail' => true,
      'output' => 'zip',
    ]);
    \ob_clean();
    if ($reportResult['is_error']) {
      throw new \CRM_Core_Exception($reportResult['messages']);
    }
    $result[] = ['messages' => $reportResult['messages']];
    \ob_start();
    $reportResult = \CRM_Report_Utils_Report::processReport([
      'instanceId' => $this->paperInstanceID,
      'sendmail' => true,
      'output' => 'zip',
    ]);
    \ob_clean();
    if ($reportResult['is_error']) {
      throw new \CRM_Core_Exception($reportResult['messages']);
    }
    $result[] = ['messages' => $reportResult['messages']];
    \Civi::settings()->set('cilb_reports_mwfreport_last_run_date', date('Y-m-d'));
  }

}
