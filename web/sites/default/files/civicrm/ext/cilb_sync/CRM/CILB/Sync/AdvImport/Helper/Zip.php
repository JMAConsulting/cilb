<?php

use CRM_CILB_Sync_ExtensionUtil as E;

class CRM_CILB_Sync_AdvImport_Helper_Zip extends CRM_Advimport_Helper_Source {
  
  /**
   * Validate the file type.
   */
  function validateUploadForm(&$form) {
    $valid = TRUE;

    $e = $form->controller->_pages['DataUpload']->getElement('uploadFile');
    $file = $e->getValue();

    \Civi::log('sync')->debug("<pre>validateUploadForm.file =>" . print_r($file, true) . "</pre>");

    if ($file['type'] != 'application/zip') {
      $form->setElementError('uploadFile', "The selected source requires a ZIP file");
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
    
    \Civi::log('sync')->debug("<pre>getDataFromFile.file =>" . print_r($file, true) . "</pre>");
    
    throw new Exception("This filetype requires a second helper implementation.");

    return [[], []];
  }

  /**
   * Import an item gotten from the queue.
   *
   * Aims to make it easy to send the data to the API,
   * you can also implement your own API calls or do direct DB queries if you prefer.
   */
  function processItem($params) {
    throw new Exception("This filetype requires a second helper implementation.");
  }




}