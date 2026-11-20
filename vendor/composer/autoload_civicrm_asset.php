<?php
global $civicrm_paths, $civicrm_setting;
$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);
$civicrm_paths['civicrm.vendor']['path'] = $vendorDir;
$civicrm_setting['domain']['userFrameworkResourceURL'] = '[cms.root]/libraries/civicrm/core';
$GLOBALS['civicrm_asset_map']['civicrm/civicrm-core']['src'] = $baseDir . '/vendor/civicrm/civicrm-core';
$GLOBALS['civicrm_asset_map']['civicrm/civicrm-core']['dest'] = $baseDir . '/web/libraries/civicrm/core';
$GLOBALS['civicrm_asset_map']['civicrm/civicrm-core']['url'] = '/libraries/civicrm/core';
$civicrm_paths['civicrm.root']['path'] = $baseDir . '/vendor/civicrm/civicrm-core/';
$civicrm_paths['civicrm.root']['url'] = '/libraries/civicrm/core/';
$GLOBALS['civicrm_asset_map']['civicrm/civicrm-packages']['src'] = $baseDir . '/vendor/civicrm/civicrm-packages';
$GLOBALS['civicrm_asset_map']['civicrm/civicrm-packages']['dest'] = $baseDir . '/web/libraries/civicrm/packages';
$GLOBALS['civicrm_asset_map']['civicrm/civicrm-packages']['url'] = '/libraries/civicrm/packages';
$civicrm_paths['civicrm.packages']['path'] = $baseDir . '/vendor/civicrm/civicrm-packages/';
$civicrm_paths['civicrm.packages']['url'] = '/libraries/civicrm/packages/';
