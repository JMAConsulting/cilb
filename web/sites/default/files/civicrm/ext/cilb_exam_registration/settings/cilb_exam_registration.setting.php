<?php

use CRM_CilbExamRegistration_ExtensionUtil as E;

return [
  'cilb_exam_registration_hidden_categories' => [
    'name' => 'cilb_exam_registration_hidden_categories',
    'title' => E::ts('Exam Categories to not show on webform'),
    'type' => 'String',
    'is_domain' => 1,
    'serialize' => CRM_Core_DAO::SERIALIZE_JSON,
    'default' => [],
    'html_type' => 'select',
    'html_attributes' => [
      'class' => 'crm-select2',
      'multiple' => 1,
    ],
    'pseudoconstant' => [
      'optionGroupName' => 'event_type',
    ],
    'settings_pages' => ['cilb_exam_registration' => ['weight' => 10]],
    'add' => '1.0',
  ],
];
