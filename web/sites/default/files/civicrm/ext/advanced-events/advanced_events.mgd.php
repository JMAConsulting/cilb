<?php

use CRM_AdvancedEvents_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_Advanced_Event_Settings',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Advanced_Event_Settings',
        'title' => E::ts('Advanced Event Settings'),
        'extends' => 'Event',
        'style' => 'Inline',
        'help_pre' => '',
        'help_post' => '',
        'weight' => 6,
        'collapse_adv_display' => TRUE,
        'is_public' => FALSE,
        'icon' => '',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Advanced_Event_Settings_CustomField_Hide_Skip_Participant_button',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Advanced_Event_Settings',
        'name' => 'Hide_Skip_Participant_button',
        'label' => E::ts('Hide "Skip Participant" button'),
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'default_value' => '0',
        'is_required' => TRUE,
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'hide_skip_participant_button',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Event_options',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Event_options',
        'title' => E::ts('Event options'),
        'extends' => 'PriceFieldValue',
        'style' => 'Inline',
        'help_pre' => E::ts('These fields will only be used if the price set is being used on an event page'),
        'help_post' => '',
        'weight' => 7,
        'collapse_adv_display' => TRUE,
        'is_public' => FALSE,
        'is_active' => TRUE,
        'icon' => '',
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Event_options_Participant_Visibility',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Event_options_Participant_Visibility',
        'title' => E::ts('Event options :: Participant Visibility'),
        'data_type' => 'String',
        'is_reserved' => FALSE,
        'option_value_fields' => [
          'name',
          'label',
          'description',
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Event_options_Participant_Visibility_OptionValue_Always',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'Event_options_Participant_Visibility',
        'label' => E::ts('Always'),
        'value' => 'always',
        'name' => 'Always',
      ],
      'match' => [
        'name',
        'option_group_id'
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Event_options_Participant_Visibility_OptionValue_Main_Participant_Only',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'Event_options_Participant_Visibility',
        'label' => E::ts('Main Participant Only'),
        'value' => 'mainparticipant',
        'name' => 'Main_Participant_Only',
      ],
      'match' => [
        'name',
        'option_group_id'
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_Event_options_Participant_Visibility_OptionValue_Additional_Participant_Only',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'Event_options_Participant_Visibility',
        'label' => E::ts('Additional Participant Only'),
        'value' => 'additionalparticipant',
        'name' => 'Additional_Participant_Only',
      ],
      'match' => [
        'name',
        'option_group_id'
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_Event_options_CustomField_Participant_Visibility',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'Event_options',
        'name' => 'Participant_Visibility',
        'label' => E::ts('Participant Visibility'),
        'html_type' => 'Select',
        'text_length' => 255,
        'note_columns' => 60,
        'note_rows' => 4,
        'column_name' => 'participant_visibility',
        'option_group_id.name' => 'Event_options_Participant_Visibility',
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
