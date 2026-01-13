<?php

class CRM_CilbReports_BAO_ReportHook extends CRM_Report_BAO_HookInterface {

  /**
   * @param $reportObj
   * @param $logTables
   */
  public function alterLogTables(&$reportObj, &$logTables) {
    if (get_class($reportObj) === 'CRM_Logging_ReportSummary') {
      $logTables['log_civicrm_participant'] = [
        'fk' => 'contact_id',
        'log_type' => 'Exam',
      ];
      $logTables['log_civicrm_contribution'] = [
        'fk' => 'contact_id',
        'log_type' => 'Payment',
      ];
    }
    else {
      $logTables[] = 'civicrm_participant';
      $logTables[] = 'civicrm_contribution';
    }
  }

  /**
   * @param $reportObj
   * @param string $table
   *
   * @return array
   */
  public function logDiffClause(&$reportObj, $table) {
    if ($table === 'log_civicrm_contribution' || $table === 'log_civicrm_participant') {
      return ['AND contact_id = %3', ''];
    }
    return [];
  }

}
