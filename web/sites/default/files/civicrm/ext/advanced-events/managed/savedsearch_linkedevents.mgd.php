<?php

use CRM_AdvancedEvents_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_AdvancedEvents_Linked_Events',
    'entity' => 'SavedSearch',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'AdvancedEvents_Linked_Events',
        'label' => E::ts('AdvancedEvents Linked Events'),
        'api_entity' => 'Event',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'title',
            'start_date',
            'end_date',
            'event_type_id:label',
            'description',
            'is_public',
            'is_online_registration',
            'is_active',
            'COUNT(Event_Participant_event_id_01.contact_id) AS COUNT_Event_Participant_event_id_01_contact_id',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [
            'id',
          ],
          'join' => [
            [
              'EventTemplate AS Event_EventTemplate_event_id_01',
              'INNER',
              [
                'id',
                '=',
                'Event_EventTemplate_event_id_01.event_id',
              ],
            ],
            [
              'Participant AS Event_Participant_event_id_01',
              'LEFT',
              [
                'id',
                '=',
                'Event_Participant_event_id_01.event_id',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_AdvancedEvents_Linked_Events_SearchDisplay_Linked_Events',
    'entity' => 'SearchDisplay',
    'cleanup' => 'always',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Linked_Events',
        'label' => E::ts('Linked Events'),
        'saved_search_id.name' => 'AdvancedEvents_Linked_Events',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [],
          'limit' => 50,
          'pager' => [],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'title',
              'dataType' => 'String',
              'label' => E::ts('Title'),
              'sortable' => TRUE,
              'link' => [
                'path' => '',
                'entity' => 'Event',
                'action' => 'view',
                'join' => '',
                'target' => '_blank',
              ],
              'title' => E::ts('View Event'),
            ],
            [
              'type' => 'field',
              'key' => 'start_date',
              'dataType' => 'Timestamp',
              'label' => E::ts('Start Date'),
              'sortable' => TRUE,
              'format' => 'dateformatshortdate',
            ],
            [
              'type' => 'field',
              'key' => 'end_date',
              'dataType' => 'Timestamp',
              'label' => E::ts('End Date'),
              'sortable' => TRUE,
              'format' => 'dateformatshortdate',
            ],
            [
              'type' => 'field',
              'key' => 'event_type_id:label',
              'dataType' => 'Integer',
              'label' => E::ts('Type'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'html',
              'key' => 'description',
              'dataType' => 'Text',
              'label' => E::ts('Description'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'is_public',
              'dataType' => 'Boolean',
              'label' => E::ts('Public'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'is_online_registration',
              'dataType' => 'Boolean',
              'label' => E::ts('Online Registration'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'COUNT_Event_Participant_event_id_01_contact_id',
              'dataType' => 'Integer',
              'label' => E::ts('Participants'),
              'sortable' => TRUE,
            ],
          ],
          'actions' => TRUE,
          'classes' => [
            'table',
            'table-striped',
          ],
          'cssRules' => [
            [
              'disabled',
              'is_active',
              '=',
              FALSE,
            ],
          ],
          'toolbar' => [
            [
              'action' => '',
              'entity' => '',
              'text' => E::ts('Add Event'),
              'icon' => 'fa-calendar-plus-o',
              'style' => 'default',
              'target' => 'crm-popup',
              'join' => '',
              'path' => 'civicrm/event/add?reset=1&action=add&template_id=[Event_EventTemplate_event_id_01.template_id]',
              'task' => '',
              'condition' => [],
            ],
          ],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
