<?php

// Angular module crmAuthnet.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
return [
  'js' => [
    'ang/afAuthnet.js',
    'ang/afAuthnet/*.js',
    'ang/afAuthnet/*/*.js',
  ],
  'css' => [
    'ang/afAuthnet.css',
  ],
  'partials' => ['ang/afAuthnet'],
  'requires' => ['crmUi', 'crmUtil', 'ngRoute'],
  'settings' => [],
];
