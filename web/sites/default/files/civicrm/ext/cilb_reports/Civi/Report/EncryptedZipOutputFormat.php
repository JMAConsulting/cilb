<?php

namespace Civi\Report;

use CRM_Report_Form;

class EncryptedZipOutputFormat extends OutputHandlerBase {

  private $zipFileName;

  public function getZipFileName(): string {
    return $this->zipFileName;
  }

  public function setZipFileName(string $fileName): \Civi\Report\EncryptedZipOutputFormat {
    $this->zipFileName = $fileName;
    return $this;
  }

  public function isOutputHandlerFor(CRM_Report_Form $form): bool {
    if (\get_class($form) === 'CRM_CilbReports_Form_Report_MWFReport' && $form->getOutputMode() === 'zip') {
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
    /** @var \CRM_CilbReports_Form_Report_MWFReport $form */
    if (str_contains($form->getReportTitle(), 'Paper')) {
      $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '_paper' . '.csv';
    }
    elseif (str_contains($form->getReportTitle(), 'cbt')) {
      $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '_cbt.csv';
    }
    else {
      $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '.csv';
    }
    $csv = \CRM_Report_Utils_Report::makeCsv($form, $rows);
    // Note that this is the same as in CRM_Report_Form::sendEmail
    $fullFileName = $this->getZipFileName();
    file_put_contents('/tmp/' . $temporaryFileName, $csv);
    $random_generated_password = \Drupal::service('password_generator')->generate('12');
    $random_password = \Civi::settings()->get('cilb_reports_mtw_password');
    if (empty($random_password)) {
      $random_password = $random_generated_password;
    }
    $zip = new \ZipArchive();
    if ($zip->open($fullFileName, \ZipArchive::CREATE) === TRUE) {
      //$zip->setPassword($random_password);
      $zip->addFile('/tmp/' . $temporaryFileName, $temporaryFileName);
      //$zip->setEncryptionName($temporaryFileName, \ZipArchive::EM_AES_256);
      $zip->setEncryptionName($temporaryFileName, \ZipArchive::EM_TRAD_PKWARE, $random_password);
      $zip->close();
    }
    \unlink('/tmp/' . $temporaryFileName);
    return $csv;
  }

  public function download() {
    $rows = $this->getForm()->getTemplate()->getTemplateVars('rows');
    $form = $this->getForm();
    $csv = \CRM_Report_Utils_Report::makeCsv($form, $rows);
    /** @var \CRM_CilbReports_Form_Report_MWFReport $form */
    if (str_contains($form->getReportTitle(), 'Paper')) {
      $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '_paper' . '.csv';
    }
    elseif (str_contains($form->getReportTitle(), 'cbt')) {
      $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '_cbt.csv';
    }
    else {
      $temporaryFileName = 'monday_wednesday_friday_report_' . date('Ymd') . '.csv';
    }
    file_put_contents('/tmp/' . $temporaryFileName, $csv);
    $fullFileName = CRM_Core_Config::singleton()->templateCompileDir . CRM_Utils_File::makeFileName($this->getFileName());
    $random_generated_password = \Drupal::service('password_generator')->generate('12');
    $random_password = \Civi::settings()->get('cilb_reports_mtw_password');
    if (empty($random_password)) {
      $random_password = $random_generated_password;
    }
    $zip = new \ZipArchive();
    if ($zip->open($fullFileName, \ZipArchive::CREATE) === TRUE) {
      $zip->setPassword($random_password);
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

  public function getCharset(): string {
    return 'utf8';
  }

  /**
   * Return the html body of the email.
   *
   * @return string
   */
  public function getMailBody():string {
    // @todo It would be nice if this was more end-user configurable, but
    // keeping it the same as it was before for now.
    $url = \CRM_Utils_System::url('civicrm/report/instance/' . $this->getForm()->getID(), 'reset=1', TRUE);
    return $this->getForm()->getReportHeader() . '<p>' . \ts('Report URL') .
      ": {$url}</p>" . '<p>' .
      \ts('The report is attached as a CSV file in the zip file.') . '</p>' .
      $this->getForm()->getReportFooter();
  }

}
