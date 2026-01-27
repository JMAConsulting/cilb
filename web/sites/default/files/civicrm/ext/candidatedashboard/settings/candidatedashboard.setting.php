<?php


return [
  'disable_activity_types' => [
    'group_name' => 'CiviCRM Preferences',
    'group' => 'core',
    'name' => 'disable_activity_types',
    'type' => 'Array',
    'html_type' => 'Select',
    'html_attributes' => [
      'multiple' => 1,
      'class' => 'crm-select2',
      'placeholder' => ts('Select Activity Type(s)'),
    ],
    'pseudoconstant' => [
      'callback' => 'CRM_Core_PseudoConstant::activityType',
    ],
    'default' => '',
    'title' => ts('Activity Type(s) to hide from display'),
    'is_domain' => 1,
    'is_contact' => 0,
    'help_text' => ts('Select activity types will hidden on New Activity backoffice form and on contact summary'),
    'settings_pages' => ['display' => ['section' => 'activity', 'weight' => 0]],
    'post_change' => ['CRM_Candidatedashboard_Utils::updateSearchKitDisplay'],
  ],
];
