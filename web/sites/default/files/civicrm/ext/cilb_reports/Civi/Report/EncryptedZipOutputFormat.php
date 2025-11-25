<?php

namespace Civi\Report;

use CRM_Report_Form;

class EncryptedZipOutputFormat extends OutputHandlerBase {

  public function isOutputHandlerFor(CRM_Report_Form $form): bool {
    if (\get_class($form) === 'CRM_CilbReports_Form_Report_MTFReport') {
      return TRUE;
    }
    return FALSE;
  }

  public function getFileName(): string {
    return 'cilbChangeReport.zip';
  }

  public function getOutputString(): string {
    $rows = $this->getForm()->getTemplate()->getTemplateVars('rows');
    $form = $this->getForm();
    return \CRM_Report_Utils_Report::makeCsv($form, $rows);
  }

  public function download() {
    $rows = $this->getForm()->getTemplate()->getTemplateVars('rows');
    $form = $this->getForm();
    $csv = \CRM_Report_Utils_Report::makeCsv($form, $rows);
    $temporaryFileName = '';
    $zip = new \ZipArchive();
    if ($zip->open($this->getFileName(), \ZipArchive::CREATE) === TRUE) {

    }
  }

  public function getMimeType(): string {
    return 'application/zip';
  }

}
