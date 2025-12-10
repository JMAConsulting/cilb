<?php

namespace Civi\Report;

use CRM_Report_Form;

class EncryptedZipOutputFormat extends OutputHandlerBase {

  public function isOutputHandlerFor(CRM_Report_Form $form): bool {
    if (\get_class($form) === 'CRM_CilbReports_Form_Report_MTFReport' && $form->getOutputMode() === 'zip') {
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
    $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '.csv';
    $csv = \CRM_Report_Utils_Report::makeCsv($form, $rows);
    // Note that this is the same as in CRM_Report_Form::sendEmail
    $fullFileName = CRM_Core_Config::singleton()->templateCompileDir . CRM_Utils_File::makeFileName($this->getFileName());
    file_put_contents('/tmp/' . $temporaryFileName, $csv);
    $random_password = \Drupal::service('password_generator')->generate('12');
    \Civi::settings()->set('cilb_reports_mtw_password', $random_password);
    $zip = new \ZipArchive();
    if ($zip->open($fullFileName, \ZipArchive::CREATE) === TRUE) {
      $zip->setPassword($random_password);
      $zip->addFile('/tmp/' . $temporaryFileName, $temporaryFileName);
      $zip->setEncryptionName($temporaryFileName, \ZipArchive::EM_AES_256);
      $zip->close();
    }
    \unlink('/tmp/' . $temporaryFileName);
    return $csv;
  }

  public function download() {
    $rows = $this->getForm()->getTemplate()->getTemplateVars('rows');
    $form = $this->getForm();
    $csv = \CRM_Report_Utils_Report::makeCsv($form, $rows);
    $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '.csv';
    file_put_contents('/tmp/' . $temporaryFileName, $csv);
    $zip = new \ZipArchive();
    if ($zip->open($this->getFileName(), \ZipArchive::CREATE) === TRUE) {
      $zip->setPassword('Test Password');
      $zip->addFile('/tmp/' . $temporaryFileName, $temporaryFileName);
      $zip->setEncryptionName($temporaryFileName, \ZipArchive::EM_AES_256);
      $zip->close();
    }
    \unlink('/tmp/' . $temporaryFileName);
    return $zip;
  }

  public function getMimeType(): string {
    return 'application/zip';
  }

  public function saveOutput(): bool {
    return FALSE;
  }

}
