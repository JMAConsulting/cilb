<?php

// TODO: advimport_id is a required param

function civicrm_api3_advimport_postimport($params) {
  $advimport_id = $params['advimport_id'];

  $dao = CRM_Core_DAO::executeQuery('SELECT * FROM civicrm_advimport WHERE id = %1', [
    1 => [$advimport_id, 'Positive'],
  ]);

  if ($dao->fetch()) {
    $classname = $dao->classname;

    $helper = new $classname;
    $helper->postImport($params);
  }
}
