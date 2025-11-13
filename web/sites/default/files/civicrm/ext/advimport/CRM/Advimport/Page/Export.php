<?php

/**
 * @file
 *
 * Exports data from an advimport table into a CSV file.
 */

class CRM_Advimport_Page_Export extends CRM_Core_Page {

  public function run() {
    CRM_Utils_System::setTitle(ts('Export errors'));

    $params = [];
    $params['id'] = CRM_Utils_Request::retrieveValue('id', 'String');

    if ($import_status = CRM_Utils_Request::retrieveValue('import_status', 'Positive')) {
      $params['status'] = $import_status;
    }

    $rows = civicrm_api3('Advimport', 'geterrors', $params)['values'];

    // Copied from CRM_Report_Utils_Report::export2csv()
    CRM_Utils_System::setHttpHeader('Content-Type', 'text/csv');
  
    //Force a download and name the file using the current timestamp.
    $datetime = date('Ymd-Gi', $_SERVER['REQUEST_TIME']);
    CRM_Utils_System::setHttpHeader('Content-Disposition', 'attachment; filename=Export_' . $datetime . '.csv');
    echo self::makeCsv($rows);
    CRM_Utils_System::civiExit();
  }

  /**
   * Generate CSV using the rows.
   * Based on CRM_Report_Utils_Report::makeCsv()
   */
  private function makeCsv($rows) {
    $config = CRM_Core_Config::singleton();
    $fieldSeparator = $config->fieldSeparator;

    // Output UTF BOM so that MS Excel copes with diacritics. This is recommended as
    // the Windows variant but is tested with MS Excel for Mac (Office 365 v 16.31)
    // and it continues to work on Libre Office, Numbers, Notes etc.
    $csv = "\xEF\xBB\xBF";

    // Add headers
    $columnHeaders = array_keys($rows[0]);
    $csv .= implode($fieldSeparator, $columnHeaders) . "\r\n";

    foreach ($rows as $row) {
      $formattedValues = [];

      foreach ($row as $value) {
        // Remove HTML, unencode entities, and escape quotation marks.
        $value = str_replace('"', '""', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML401));
        $formattedValues[] = '"' . $value . '"';
      }

      $csv .= implode($fieldSeparator, $formattedValues) . "\r\n";
    }

    return $csv;
  }

}
