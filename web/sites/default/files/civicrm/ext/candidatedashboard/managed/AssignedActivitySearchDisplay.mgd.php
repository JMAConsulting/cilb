<?php
use CRM_Candidatedashboard_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_ActivitySearch',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'ActivitySearch',
        'label' => E::ts('Search Activities'),
        'api_entity' => 'Activity',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'activity_type_id:label',
            'subject',
            'activity_date_time',
            'status_id:label',
            'details',
          ],
          'orderBy' => [],
          'where' => [
            [
              'is_deleted',
              '=',
              FALSE,
            ],
          ],
          'groupBy' => [
            'id',
          ],
          'join' => [
            [
              'Contact AS Activity_ActivityContact_Contact_01',
              'LEFT',
              'ActivityContact',
              [
                'id',
                '=',
                'Activity_ActivityContact_Contact_01.activity_id',
              ],
              [
                'Activity_ActivityContact_Contact_01.record_type_id:name',
                '=',
                '"Activity Targets"',
              ],
            ],
            [
              'Contact AS Activity_ActivityContact_Contact_02',
              'LEFT',
              'ActivityContact',
              [
                'id',
                '=',
                'Activity_ActivityContact_Contact_02.activity_id',
              ],
              [
                'Activity_ActivityContact_Contact_02.record_type_id:name',
                '=',
                '"Activity Source"',
              ],
            ],
            [
              'Contact AS Activity_ActivityContact_Contact_03',
              'LEFT',
              'ActivityContact',
              [
                'id',
                '=',
                'Activity_ActivityContact_Contact_03.activity_id',
              ],
              [
                'Activity_ActivityContact_Contact_03.record_type_id:name',
                '=',
                '"Activity Assignees"',
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
    'name' => 'SavedSearch_ActivitySearch_SearchDisplay_Search_Activities',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Search_Activities',
        'label' => E::ts('Find Activities'),
        'saved_search_id.name' => 'ActivitySearch',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [
            [
              'activity_date_time',
              'DESC',
            ],
          ],
          'limit' => 0,
          'pager' => FALSE,
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'activity_type_id:label',
              'label' => E::ts('Type'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'subject',
              'label' => E::ts('Subject'),
              'sortable' => TRUE,
              'link' => [
                'path' => 'civicrm/my-activity#?id=[id]',
                'entity' => '',
                'action' => '',
                'join' => '',
                'target' => 'crm-popup',
                'task' => '',
              ],
              'empty_value' => 'No Subject',
            ],
            [
              'type' => 'html',
              'key' => 'details',
              'dataType' => 'Text',
              'label' => E::ts('Details'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'activity_date_time',
              'label' => E::ts('Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'status_id:label',
              'label' => E::ts('Status'),
              'sortable' => TRUE,
            ],
          ],
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
          ],
          'headerCount' => FALSE,
        ],
        'acl_bypass' => TRUE,
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
