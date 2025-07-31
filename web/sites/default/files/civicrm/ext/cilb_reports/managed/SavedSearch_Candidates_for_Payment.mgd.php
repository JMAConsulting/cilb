<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_Candidates_for_Payment',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Candidates_for_Payment',
        'label' => E::ts('Candidates for Payment'),
        'api_entity' => 'Participant',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'contact_id.display_name',
            'event_id.title',
            'register_date',
            'role_id:label',
            'status_id:label',
            'Participant_Event_event_id_01.Exam_Details.External_Fee_Amount',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [
            [
              'Event AS Participant_Event_event_id_01',
              'LEFT',
              [
                'event_id',
                '=',
                'Participant_Event_event_id_01.id',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_Candidates_for_Payment_SearchDisplay_Candidates_for_Payment',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Candidates_for_Payment',
        'label' => E::ts('Candidates for Payment'),
        'saved_search_id.name' => 'Candidates_for_Payment',
        'type' => 'table',
        'settings' => [
          'description' => E::ts(''),
          'sort' => [],
          'limit' => 50,
          'pager' => FALSE,
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'id',
              'dataType' => 'Integer',
              'label' => E::ts('Candidate ID'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'contact_id.display_name',
              'dataType' => 'String',
              'label' => E::ts('Candidate'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'event_id.title',
              'dataType' => 'String',
              'label' => E::ts('Exam Title'),
              'sortable' => TRUE,
              'link' => [
                'path' => 'civicrm/contact/view/participant?action=view&reset=1&id=[id]&cid=[contact_id]',
                'entity' => '',
                'action' => '',
                'join' => '',
                'target' => 'crm-popup',
                'task' => '',
              ],
              'title' => E::ts(NULL),
            ],
            [
              'type' => 'field',
              'key' => 'register_date',
              'dataType' => 'Timestamp',
              'label' => E::ts('Register date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'role_id:label',
              'dataType' => 'String',
              'label' => E::ts('Role'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'status_id:label',
              'dataType' => 'Integer',
              'label' => E::ts('Status'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'Participant_Event_event_id_01.Exam_Details.External_Fee_Amount',
              'dataType' => 'Float',
              'label' => E::ts('Exam External Fee Amount'),
              'sortable' => TRUE,
            ],
          ],
          'actions' => FALSE,
          'classes' => ['table', 'table-striped'],
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
