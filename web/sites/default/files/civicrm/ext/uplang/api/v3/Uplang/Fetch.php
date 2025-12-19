<?php

function _civicrm_api3_uplang_fetch_spec(&$params) {
  $params['locales'] = [
    'title' => 'Locales',
    'type' => CRM_Utils_Type::T_STRING,
    'description' => 'Comma-delimited list of additional languages that need to be fetched',
    'api.default' => '',
  ];
  $params['forceDownload'] = [
    'title' => 'Force download',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'description' => 'Force download of translation files if we already downloaded an up to date (within the last 1 day) translation.',
    'api.default' => FALSE,
  ];
}

/**
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws \CRM_Core_Exception
 */
function civicrm_api3_uplang_fetch($params) {
  $downloaded = CRM_Uplang_Utils::updateAllFiles($params['locales'] ?? NULL);
  return civicrm_api3_create_success($downloaded, $params, 'Uplang', 'Fetch');
}
