<?php

/**
 * Advimport.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_advimport_get($params) {
  // FIXME: this is not returning 'values' because it is fed directly to the Angular resolver.
  $result = [
    'values' => [],
  ];

  Civi::log()->warning('advimport: api3 advimport.get is deprecated and does not enforce permissions correctly. It will soon be removed. Please use the api4 advimport.get');

  // FIXME: we should get the headers from the mapping/helper class
  // but until then, we can guess from the DB table.
  $dao = CRM_Core_DAO::executeQuery("SHOW TABLES LIKE 'civicrm_advimport_%'");

  while ($dao->fetch()) {
    $foo = CRM_Advimport_Upload_Form_MapFields::convertDaoToArray($dao, FALSE);

    foreach ($foo as $key => $val) {
      if (substr($key, 0, 10) == 'Tables_in_') {
        $key = substr($val, strlen('civicrm_advimport_'));
        $result['values'][] = $key;
      }
    }
  }

  return $result;
}
