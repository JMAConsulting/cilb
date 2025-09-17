<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Helper_PHPExcel extends CRM_Advimport_Helper_Source {

  /**
   * Validate the file type.
   */
  function validateUploadForm(&$form) {
    $valid = true;

    $e = $form->controller->_pages['DataUpload']->getElement('uploadFile');
    $file = $e->getValue();
    $tmp_file = $file['tmp_name'];
    $file_type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($tmp_file);

    if (!in_array($file_type, ['Csv', 'Xlsx', 'Ods'])) {
      $form->setElementError('uploadFile', E::ts("The file must be of type ODS (LibreOffice), XLSX (Excel) or CSV."));
      $valid = false;
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
    $file_type = \PhpOffice\PhpSpreadsheet\IOFactory::identify($file);
    $objReader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($file_type);
    $objReader->setReadDataOnly(TRUE);

    if ($file_type == 'Csv') {
      if ($delimiter) {
        $objReader->setDelimiter($delimiter);
      }
      $objReader->setInputEncoding($encoding);
    }

    $objPHPExcel = $objReader->load($file);
    $calculate_cells = ($file_type == 'Csv') ? FALSE : TRUE; // Avoid issue with char '=', #1
    $datatmp = $objPHPExcel->getActiveSheet()->toArray(NULL, $calculate_cells, TRUE, TRUE);

    // Remove the header
    $headers = $datatmp[1];
    unset($datatmp[1]);

    // Re-key data using the headers
    $data = [];

    foreach ($datatmp as $val) {
      $i = 0;

      foreach ($val as $kk => $vv) {
        if (empty($headers[$kk])) {
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
   * Helper function to convert an Excel date (a weird integer) to ISO.
   * This might be a bug in how we use phpSpreadsheet, but meanwhile this function helps to workaround.
   * Ex: https://stackoverflow.com/q/11119631
   */
  function excelDateToISO($date) {
    $unix = ($date - 25569) * 86400;
    return gmdate("Y-m-d H:i:s", $unix);
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
