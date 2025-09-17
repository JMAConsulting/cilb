<?php

class CRM_Advimport_Report_Form {

  /**
   * @see advimport_civicrm_buildForm().
   */
  static public function buildForm(&$form) {
    // Insert the "Export to Excel" task before "Export to CSV"
    if ($form->elementExists('task')) {
      $e = $form->getElement('task');

      $tasks = CRM_Report_BAO_ReportInstance::getActionMetadata();
      $helpers = CRM_Advimport_BAO_Advimport::getHelpers();

      foreach ($helpers as $h) {
        if (empty($h['type']) || $h['type'] != 'report-batch-update') {
          continue;
        }

        $tasks['report_instance.advimport.' . $h['class']] = [
          'title' => $h['label'],
        ];
      }

      $form->removeElement('task');

      // Based on CRM_Report_BAO_ReportInstance
      // @todo Seems complicated. Re-check if there is a cleaner way?
      $form->assign('taskMetaData', $tasks);
      $select = $form->add('select', 'task', NULL, ['' => ts('Actions')], FALSE, [
        'class' => 'crm-select2 crm-action-menu fa-check-circle-o huge crm-search-result-actions',
      ]);

      foreach ($tasks as $key => $task) {
        $attributes = [];
        if (isset($task['data'])) {
          foreach ($task['data'] as $dataKey => $dataValue) {
            $attributes['data-' . $dataKey] = $dataValue;
          }
        }
        $select->addOption($task['title'], $key, $attributes);
      }
    }

    // civiexportexcel calls this the "legacy" mode, until we fix this:
    // https://github.com/civicrm/civicrm-core/pull/17145
    $selectedTask = CRM_Utils_Request::retrieveValue('task', 'String');

    if ($selectedTask) {
      $parts = explode('.', $selectedTask);

      if (count($parts) == 3 && $parts[1] == 'advimport') {
        $classname = $parts[2];
        $helper = new $classname;
        $helper->reportBulkUpdateSetup($form);
      }
    }
  }

}
