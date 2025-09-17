<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Helper_ReportBatchUpdate {

  /**
   * Based on civiexportexcel_legacyBuildFormExport().
   * We should remove it when we fix core report handling
   * https://github.com/civicrm/civicrm-core/pull/17145
   *
   * This duplicates part of CRM_Report_Form::postProcess()
   * since we do not have a place to hook into, we hi-jack the form process
   * before it gets into postProcess.
   */
  public function reportBulkUpdateSetup($form) {
    $output = CRM_Utils_Request::retrieve('output', 'String', CRM_Core_DAO::$_nullObject);
    $form->assign('printOnly', TRUE);
    $printOnly = TRUE;
    $form->assign('outputMode', 'excel2007');


    // get ready with post process params
    $form->beginPostProcess();

    // build query
    $sql = $form->buildQuery(FALSE);

    // build array of result based on column headers. This method also allows
    // modifying column headers before using it to build result set i.e $rows.
    $rows = [];
    $form->buildRows($sql, $rows);

    // format result set.
    // This seems to cause more problems than it fixes.
    // $form->formatDisplay($rows);

    // assign variables to templates
    $form->doTemplateAssignment($rows);

    $headers = array_keys($rows[0]);

    $headers[] = 'amount_paid';
    $headers[] = 'test';

    $table_name = CRM_Advimport_BAO_Advimport::saveToDatabaseTable($headers, $rows);

    if ($table_name) {
      // Create a new entry in civicrm_advimport
      $result = \Civi\Api4\Advimport::create(FALSE)
        ->addValue('contact_id', CRM_Core_Session::singleton()->get('userID'))
        ->addValue('table_name', $table_name)
        ->addValue('classname', get_class($this))
        ->addValue('filename', E::ts('Report Batch Update'))
        ->execute()
        ->first();

      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/a/#/advimport/' . $result['id']));
    }

  }

}
