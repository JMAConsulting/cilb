<?php

use CRM_CILB_Sync_ExtensionUtil as E;

class CRM_CILB_Sync_AdvImport_Helper_Dat extends CRM_Advimport_Helper_Csv {
  
  /**
   * Validate the file type.
   */
  function validateUploadForm(&$form) {
    $valid = TRUE;

    $e = $form->controller->_pages['DataUpload']->getElement('uploadFile');
    $file = $e->getValue();
    $file['extension'] = pathinfo($file['name'], PATHINFO_EXTENSION);

    if ($file['type'] != 'application/octet-stream' || $file['extension'] != 'dat') {
      $form->setElementError('uploadFile', "The selected source must be a DAT file");
      $valid = FALSE;
    }

    if ($valid ) {
      return parent::validateUploadForm($form);
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
    $delimiter = "\t"; // Requires double quotes

    return parent::getDataFromFile($file, $delimiter, $encoding);
  }

}