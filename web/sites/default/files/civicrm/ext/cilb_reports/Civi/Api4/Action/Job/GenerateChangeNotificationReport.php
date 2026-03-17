<?php

namespace Civi\Api4\Action\Job;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Generate the Change Notification Report to be emailed to the client
 *
 * @method int getReportInstanceID()
 * @method $this setReportInstanceID(int $reportInstanceID)
 * @method bool getRunInNonProductionEnvironment()
 * @method $this setRunInNonProductionEnvironment(bool $runInNonProductionEnvironment)
 * @method string getLanguage()
 * @method $this setLanguage(string $language)
 */
class GenerateChangeNotificationReport extends AbstractAction {

  /**
   * @var bool
   */
  protected $runInNonProductionEnvironment = TRUE;

  /**
   * @var null
   */
  protected $language = NULL;

  /**
   * Change Notification Report Instance ID
   * @var int
   */
  protected $reportInstanceID;

  public function _run(Result $result) {
    \ob_start();
    $reportResult = \CRM_Report_Utils_Report::processReport([
      'instanceId' => $this->reportInstanceID,
      'sendmail' => true,
      'output' => 'zip',
    ]);
    \ob_clean();
    if ($reportResult['is_error']) {
      throw new \CRM_Core_Exception($reportResult['messages']);
    }
    $result[] = ['messages' => $reportResult['messages']];
    \Civi::settings()->set('cilb_reports_changenotification_last_run_date', date('Y-m-d'));
  }

}
