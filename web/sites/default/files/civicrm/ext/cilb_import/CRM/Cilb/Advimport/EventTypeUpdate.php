<?php

use CRM_Cilb_Import_ExtensionUtil as E;

class CRM_Cilb_Advimport_EventTypeUpdate extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Returns a human-readable name for this helper.
   */
  function getHelperLabel() {
    return E::ts("Update Event Type label and description");
  }

  /**
   * By default, a field mapping will be shown, but unless you have defined
   * one in getMapping() - example later below - you may want to skip it.
   * Displaying it is useful for debugging at first.
   */
  function mapfieldMethod() {
    //return 'skip';
  }

  function getDataFromFile($file, $delimiter = ',', $encoding = 'UTF-8') {
    // windows friendly
    try {
      return parent::getDataFromFile($file, ';', 'Windows-1252');
    }
    catch (Exception $e) {
      Civi::log()->debug( $e->getMessage());
    }
  }

  /**
   * Available fields.
   */
  function getMapping(&$form) {
    $map = [
      'option_group_id' => [
        'label' => 'Option Group ID',
        'field' => 'option_group_id',
        'required' => TRUE,
        'validate' => 'String',
        'aliases' => ['option_group_id', 'og_id', 'og']
      ],
      'label_en_US' => [
        'label' => E::ts('Label (English)'),
        'field' => 'label_en_US',
        'required' => TRUE,
        'validate' => 'String',
        'example' => 'Air A',
      ],
      'label_es_MX' => [
        'label' => E::ts('Label (Spanish)'),
        'field' => 'label_en_US',
        'required' => TRUE,
        'validate' => 'String',
        'example' => 'Aire A',
      ],
      'description_en_US' => [
        'label' => E::ts('Description (English)'),
        'field' => 'description_en_US',
        'required' => TRUE,
        'validate' => 'String',
      ],
      'description_es_MX' => [
        'label' => E::ts('Description (Spanish)'),
        'field' => 'description_es_MX',
        'required' => TRUE,
        'validate' => 'String',
      ],
    ];

    return $map;
  }

  static function excelDateToPhp($value) {
    // Only convert if it's numeric and within Excel's date range
    if (is_numeric($value) && $value > 25569 && $value < 60000) {
        // Excel's epoch (1900-01-01), minus 2 days to adjust for Excel bug
        $unixTimestamp = ($value - 25569) * 86400;
        return gmdate("Y-m-d", $unixTimestamp);
    }
    return $value; // Return as-is if not a date
}

  /**
   * Import an item gotten from the queue.
   *
   * This is where, in custom PHP import scripts, you would program all
   * the logic on how to handle imports the old fashioned way.
   */
  function processItem($params) {

    $message = 0;
    $params['option_group_id'] = $params['option_group_id'] ?? 15;
    $locale = ['en_US', 'es_MX'];
    $id = NULL;
    foreach ($locale as $lang) {
      if (!$id) {
        $id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_option_value WHERE option_group_id = %1 AND label_$lang = %2 LIMIT 1", [
         1 => [$params['option_group_id'], 'Int'],
         2 => [$params['label_' . $lang], 'String'],
        ], TRUE, FALSE);
      }
    }

   if ($id) {
     $cols = [];
     $dbparams = [[$id, 'Int']];
     $i = 1;
     foreach (['label', 'description'] as $field) {
       foreach ($locale as $lang) {
         $cols[] = "{$field}_{$lang} = %$i";
         $dbparams[$i] = [$params["{$field}_{$lang}"], 'String'];
         $i++;
       }
     }
     $query = "UPDATE civicrm_option_value SET " . implode(', ', $cols) . " WHERE id = %0";
     $dao = new CRM_Core_DAO();
     $query = CRM_Core_DAO::composeQuery($query, $dbparams, TRUE);
     $dao->query($query, FALSE);
   }

  }


}
