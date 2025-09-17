<?php

use CRM_Advimport_ExtensionUtil as E;

/**
 * Provided as a proof of concept mostly. It has serious limitations.
 *
 * To import MS Access (MDB) files, you will need to install the following packages on the server:
 *
 * ```
 * apt-get install php7.2-odbc odbc-mdbtools unixodbc-dev
 * ```
 *
 * (exact names may depend on your Linux distribution or PHP version).
 *
 * Source: https://gist.github.com/amirkdv/9672857
 */

class CRM_Advimport_Helper_MsAccess extends CRM_Advimport_Helper_Source {

  /**
   * Validate the file type.
   */
  function validateUploadForm(&$form) {
    $valid = true;

    if (!function_exists('odbc_connect')) {
      $form->setElementError('uploadFile', E::ts('The php-odbc package must be enabled.'));
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
    throw new Exception("Do not call this function directly, override it. See this function's code comments for details.");

    // Example
    // NB: columns that are varchar/text over 127 chars will return as empty!
    // https://stackoverflow.com/questions/34217558/mdbtools-driver-not-returning-string-values-with-php-ms-access
    $connection = new \PDO("odbc:Driver=MDBTools;DBQ=$file");
    $data = $connection->query('SELECT * FROM MyTable')->fetchAll(\PDO::FETCH_ASSOC);
    $headers = array_keys($data[0]);
    return [$headers, $data];
  }

  /**
   * Import an item gotten from the queue.
   *
   * Aims to make it easy to send the data to the API,
   * you can also implement your own API calls or do direct DB queries if you prefer.
   */
  function processItem($params) {
    throw new Exception('Override this function.');
  }

}
