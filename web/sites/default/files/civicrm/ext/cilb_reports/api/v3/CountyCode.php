<?php

function civicrm_api3_county_code_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params, 'CountyCode');
}

/**
 * Returns array of assignments matching a set of one or more group properties
 *
 * @param array $params  Associative array of property name/value pairs
 *                       describing the assignments to be retrieved.
 * @example
 * @return array ID-indexed array of matching assignments
 * {@getfields assignment_get}
 * @access public
 */
function civicrm_api3_county_code_get($params) {
  if (!empty($params['id'])) {
   $params['return'] = ['county_code', 'county'];
   return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params, TRUE, 'CountyCode');
  }
  return civicrm_api3_create_success(CRM_CilbReports_BAO_CountyCode::retrieve($params), $params, 'CountyCode', 'get');
}


function _civicrm_api3_county_code_getlist_output ($result, $request, $entity, $fields) {
  $output = [];
  if (!empty($result['values'])) {
    foreach ($result['values'] as $key => $row) {
      $data = [
        'id' => $row['id'],
        'label' => $row['county_code']  ? $row['county'] . ' - ' . $row['county_code'] : $row['id'],
      ];
      $output[] = $data;
    }
  }

  return $output;
}
