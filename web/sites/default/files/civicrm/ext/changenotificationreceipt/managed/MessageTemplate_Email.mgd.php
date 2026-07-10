<?php

use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

return [
  [
    'name' => 'MessageTemplate_ChangeNotificationEmail',
    'entity' => 'MessageTemplate',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'msg_title' => 'CILB Change Notification - Email',
        'msg_subject' => 'Update to your CILB Exam information',
        'msg_text' => 'A recent change was made to your CILB Exam contact information or exam registration. Attached is an updated receipt.',
        'msg_html' => '<p>A recent change was made to your CILB Exam contact information or exam registration. Attached is an updated receipt.</p>',
        'is_active' => TRUE,
        'is_reserved' => FALSE,
      ],
    ],
  ],
];
