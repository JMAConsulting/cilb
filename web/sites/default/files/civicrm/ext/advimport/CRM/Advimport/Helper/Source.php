<?php

use CRM_Advimport_ExtensionUtil as E;

abstract class CRM_Advimport_Helper_Source {

  protected $helperDefinition;

  function __construct($helperDefinition = NULL) {
    $this->helperDefinition = $helperDefinition;
  }

  function getHelperDefinition() {
    return $this->helperDefinition;
  }

  /**
   * Available fields.
   *
   * @param $form
   *   Some helpers (ex: APIv3) use this to determine the state of the
   *   form and whether to add more fields.
   */
  function getMapping(&$form) {
    $map = [];
    $headers = [];

    if (isset($form)) {
      $headers = $form->controller->get('headers');
    }

    foreach ($headers as $h) {
      // FIXME I forget why this prefix gets added, and would rather not have this code:
      if (substr($h, 0, 4) == 'col_') {
        $h = substr($h, 4);
      }

      $map[$h] = [
        'label' => $h,
        'field' => $h,
        // These are not checked at the moment
        // but are kept here as reminders of things to consider eventually.
        'required' => FALSE,
        'validate' => 'String',
      ];
    }

    return $map;
  }

  /**
   * Returns a human-readable name for this helper.
   */
  function getHelperLabel() {
    return E::ts("Import data");
  }

  /**
   * Makes it possible to customize the name of the group or tag
   * that will be greated for the imported contacts.
   */
  function getGroupOrTagLabel($params) {
    $filename = $params['filename'];

    // Strip the extension
    $filename = preg_replace('/\.[a-zA-Z]{3,4}$/', '', $filename);

    return E::ts('Import (%1) at %2', [
      1 => $filename,
      2 => date('Y-m-d H:m:i'),
    ]);
  }

  /**
   * Validate the file type.
   */
  abstract function validateUploadForm(&$form);

  /**
   * Returns the data from the file.
   *
   * @param $file
   * @param $delimiter
   * @param $encoding
   *
   * @returns Array
   */
  abstract function getDataFromFile($file, $delimiter = '', $encoding = 'UTF-8');

  /**
   * Import an item gotten from the queue.
   *
   * Aims to make it easy to send the data to the API,
   * you can also implement your own API calls or do direct DB queries if you prefer.
   */
  abstract function processItem($params);

}
