<?php

use CRM_Ses_ExtensionUtil as E;

return [
  'ses_access_key' => [
    'name' => 'ses_access_key',
    'type' => 'String',
    'html_type' => 'text',
    'default' => NULL,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('SES Access Key'),
    'description' => E::ts('Usually a 20-ish character alphanumeric key.'),
    'html_attributes' => [
      'size' => 60,
    ],
    'settings_pages' => [
      'ses' => [
        'weight' => 5,
      ],
    ],
  ],
  'ses_secret_key' => [
    'name' => 'ses_secret_key',
    'type' => 'String',
    'html_type' => 'text',
    'default' => NULL,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('SES Secret Key'),
    'description' => E::ts('Usually a 40-ish character alphanumeric key.'),
    'html_attributes' => [
      'size' => 60,
    ],
    'settings_pages' => [
      'ses' => [
        'weight' => 10,
      ],
    ],
  ],
  'ses_region' => [
    'name' => 'ses_region',
    'type' => 'String',
    'html_type' => 'text',
    'default' => NULL,
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('SES Region'),
    'description' => E::ts('Must be the one setup on your account. Ex: ca-central-1, us-east-1, etc.'),
    'html_attributes' => [],
    'settings_pages' => [
      'ses' => [
        'weight' => 15,
      ],
    ],
  ],
  'ses_suppression_list_removal' => [
    'name' => 'ses_suppression_list_removal',
    'type' => 'Boolean',
    'html_type' => 'checkbox',
    'default' => FALSE,
    'add' => '1.4',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('Remove from SES suppression list on un-hold'),
    'description' => E::ts('When enabled, taking a contact\'s email address off hold in CiviCRM will call the Amazon SES DeleteSuppressedDestination API to remove it from your SES account-level suppression list. Requires the ses:DeleteSuppressedDestination IAM permission to be granted to the configured IAM user, in addition to the existing sending permission.'),
    'html_attributes' => [],
    'settings_pages' => [
      'ses' => [
        'weight' => 20,
      ],
    ],
  ],
  'ses_sns_topic_arn' => [
    'name' => 'ses_sns_topic_arn',
    'type' => 'String',
    'html_type' => 'text',
    'default' => NULL,
    'add' => '1.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => E::ts('SNS Topic ARN (optional)'),
    'description' => E::ts('If set, the bounce/complaint webhook only accepts SNS notifications whose TopicArn matches this exact value (e.g. arn:aws:sns:us-east-1:123456789012:my-topic). Recommended: it stops anyone from replaying validly-signed notifications published from a different SNS topic to your public webhook. Leave blank to accept any topic (previous behaviour).'),
    'html_attributes' => [
      'size' => 60,
    ],
    'settings_pages' => [
      'ses' => [
        'weight' => 25,
      ],
    ],
  ],
];
