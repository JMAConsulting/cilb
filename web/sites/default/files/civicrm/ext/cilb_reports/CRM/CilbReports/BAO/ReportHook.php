<?php

class CRM_CilbReports_BAO_ReportHook extends CRM_Report_BAO_HookInterface {

  /**
   * @param $reportObj
   * @param $logTables
   */
  public function alterLogTables(&$reportObj, &$logTables) {
    if (get_class($reportObj) === 'CRM_Logging_ReportSummary' || get_class($reportObj) === 'CRM_Report_Form_Contact_LoggingSummary') {
      $logTables['log_civicrm_participant'] = [
        'fk' => 'contact_id',
        'log_type' => 'Exam',
      ];
      $logTables['log_civicrm_contribution'] = [
        'fk' => 'contact_id',
        'log_type' => 'Payment',
      ];
    }
  }

  /**
   * @param $reportObj
   * @param string $table
   *
   * @return array
   */
  public function logDiffClause(&$reportObj, $table) {
    return [];
  }

}
