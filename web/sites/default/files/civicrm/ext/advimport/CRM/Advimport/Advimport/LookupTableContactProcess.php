<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_LookupTableContactProcess extends CRM_Advimport_Helper_PHPExcel {

  /**
   * Returns a human-readable name for this helper.
   */
  function getHelperLabel() {
    return E::ts('Lookup Table to Batch Update Contacts - Processor');
  }

  /**
   * By default, a field mapping will be shown, but unless you have defined
   * one in getMapping() - example later below - you may want to skip it.
   * Displaying it is useful for debugging at first.
   */
  function mapfieldMethod() {
    return 'skip';
  }

  /**
   * Import an item gotten from the queue.
   */
  function processItem($params) {
    // Get the api4 field name
    $mapping = CRM_Core_DAO::singleValueQuery('SELECT mapping FROM civicrm_advimport WHERE table_name = %1', [
      1 => [$params['import_table_name'], 'String'],
    ]);
    $mapping = json_decode($mapping, TRUE);
    $new_field = $mapping['new_field'];
    // Check if we match on the OptionValue.value, or OptionValue.label, if applicable
    if ($mapping['match_type'] == 2) {
      $new_field .= ':label';
    }

    if (empty($params[$mapping['new_field']])) {
      throw new Exception(E::ts('New value not found in the lookup table'));
    }

    $result = \Civi\Api4\Contact::update(FALSE)
      ->addValue($new_field, $params[$mapping['new_field']])
      ->addWhere('id', '=', $params['entity_id'])
      ->execute()
      ->first();
  }

}
