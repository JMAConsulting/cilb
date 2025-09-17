<?php

use CRM_Advimport_ExtensionUtil as E;
use League\Csv\Reader;

class CRM_Advimport_Helper_Csv extends CRM_Advimport_Helper_Source {

  /**
   * Validate the file type.
   */
  function validateUploadForm(&$form) {
    $valid = TRUE;

    $e = $form->controller->_pages['DataUpload']->getElement('uploadFile');
    $file = $e->getValue();
    $tmp_file = $file['tmp_name'];

    try {
      Reader::createFromPath($tmp_file);
    }
    catch (Exception $e) {
      $error = $e->getMessage();
      $form->setElementError('uploadFile', $error);
      $valid = FALSE;
    }

    return $valid;
  }

  /**
   * Returns the data from the file.
   * Supports CSV, Excel and ODS (whatever PHPExcel supports).
   *
   * @param $file
   * @param $delimiter
   * @param $encoding
   *
   * @returns Array
   */
  function getDataFromFile($file, $delimiter = '', $encoding = 'UTF-8') {
    // Use the fieldSeparator by default, it defaults to ','
    if (empty($delimiter)) {
      $delimiter = Civi::settings()->get('fieldSeparator');
    }

    $csv = Reader::createFromPath($file);
    $csv->setDelimiter($delimiter);

    $csv->setHeaderOffset(0);
    $headers = $csv->getHeader();
    // Avoid "zero as false" errors by rekeying.
    $headers = array_combine($headers, $headers);
    $records = $csv->getRecords();

    // Re-key data using the headers
    $data = [];

    foreach ($records as $val) {
      $i = 0;

      foreach ($val as $kk => $vv) {
        if (!isset($headers[$kk])) {
          unset($headers[$kk]);
          // Skip empty headers
          continue;
        }
        $d[$headers[$kk]] = $vv;
        $i++;
      }

      // Remove fields we do not want to re-import
      // Has to be done after, so that the order of colums is respected
      unset($d['import_status']);
      unset($d['import_error']);

      $data[] = $d;
    }

    return [array_values($headers), $data];
  }

  /**
   * Import an item gotten from the queue.
   *
   * Aims to make it easy to send the data to the API,
   * you can also implement your own API calls or do direct DB queries if you prefer.
   */
  function processItem($params) {
    // Convert external_identifier to Contact ID
    $params['contact_id'] = CRM_Core_DAO::singleValueQuery('SELECT id FROM civicrm_contact WHERE external_identifier = %1', [1=>[$params['external_id'], 'String']]);

    if (empty($params['contact_id'])) {
      throw new Exception("Contact not found for external ID: {$params['external_id']}");
    }

    unset($params['external_id']);

    unset($params['advimport_id']);
    unset($params['group_or_tag']);
    unset($params['group_or_tag_id']);
    unset($params['import_table_name']);
    unset($params['import_row_id']);

    civicrm_api3('Contact', 'create', $params);
  }

}
