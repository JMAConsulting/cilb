<?php
use CRM_CilbReports_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_Candidate_Reconciliation_Report',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Candidate_Reconciliation_Report',
        'label' => E::ts('Candidate Reconciliation Report'),
        'form_values' => [
          'join' => [
            'Participant_Contribution_Candidate_Payment_01' => 'Candidate Payment',
          ],
        ],
        'api_entity' => 'Participant',
        'api_params' => [
          'version' => 4,
          'select' => [
            'Participant_Contribution_Candidate_Payment_01.receive_date',
            'Participant_Contribution_Candidate_Payment_01.trxn_id',
            'Participant_Contribution_Candidate_Payment_01.id',
            'contact_id.sort_name',
            'Participant_Contribution_Candidate_Payment_01.payment_instrument_id:label',
            'Participant_Contribution_Candidate_Payment_01.check_number',
            'SUM(Participant_Contribution_Candidate_Payment_01_Contribution_EntityFinancialTrxn_FinancialTrxn_01.amount) AS SUM_Participant_Contribution_Candidate_Payment_01_Contribution_EntityFinancialTrxn_FinancialTrxn_01_amount',
            'Participant_Contribution_Candidate_Payment_01.total_amount',
            'id',
            'Participant_Event_event_id_01.start_date',
            'Participant_Event_event_id_01.event_type_id:label',
            'Participant_Webform.Candidate_Representative_Name',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [
            'id',
            'Participant_Event_event_id_01.id',
            'Participant_Contribution_Candidate_Payment_01.id',
          ],
          'join' => [
            [
              'Event AS Participant_Event_event_id_01',
              'INNER',
              [
                'event_id',
                '=',
                'Participant_Event_event_id_01.id',
              ],
            ],
            [
              'Contact AS Participant_Contact_contact_id_01',
              'LEFT',
              [
                'contact_id',
                '=',
                'Participant_Contact_contact_id_01.id',
              ],
            ],
            [
              'Contribution AS Participant_Contribution_Candidate_Payment_01',
              'INNER',
              [
                'Participant_Webform.Candidate_Payment',
                '=',
                'Participant_Contribution_Candidate_Payment_01.id',
              ],
            ],
            [
              'FinancialTrxn AS Participant_Contribution_Candidate_Payment_01_Contribution_EntityFinancialTrxn_FinancialTrxn_01',
              'LEFT',
              'EntityFinancialTrxn',
              [
                'Participant_Contribution_Candidate_Payment_01.id',
                '=',
                'Participant_Contribution_Candidate_Payment_01_Contribution_EntityFinancialTrxn_FinancialTrxn_01.entity_id',
              ],
              [
                'Participant_Contribution_Candidate_Payment_01_Contribution_EntityFinancialTrxn_FinancialTrxn_01.entity_table',
                '=',
                '\'civicrm_contribution\'',
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
    'name' => 'SavedSearch_Candidate_Reconciliation_Report_SearchDisplay_Candidate_Reconciliation_Report',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Candidate_Reconciliation_Report',
        'label' => E::ts('Candidate Reconciliation Report'),
        'saved_search_id.name' => 'Candidate_Reconciliation_Report',
        'type' => 'table',
        'settings' => [
          'description' => E::ts(''),
          'sort' => [
            [
              'Participant_Contribution_Candidate_Payment_01.receive_date',
              'ASC',
            ],
          ],
          'limit' => 50,
          'pager' => [],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'Participant_Contribution_Candidate_Payment_01.receive_date',
              'dataType' => 'Timestamp',
              'label' => E::ts('Trans Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'Participant_Contribution_Candidate_Payment_01.trxn_id',
              'dataType' => 'String',
              'label' => E::ts('Trans#'),
              'sortable' => TRUE,
              'empty_value' => '-',
              'rewrite' => '',
            ],
            [
              'type' => 'field',
              'key' => 'Participant_Event_event_id_01.start_date',
              'dataType' => 'Timestamp',
              'label' => E::ts('Exam Date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'contact_id.sort_name',
              'dataType' => 'String',
              'label' => E::ts('Applicant Name'),
              'sortable' => TRUE,
              'link' => [
                'path' => '',
                'entity' => 'Contact',
                'action' => 'view',
                'join' => 'contact_id',
                'target' => '',
              ],
              'title' => E::ts('View Contact'),
              'rewrite' => '',
            ],
            [
              'type' => 'field',
              'key' => 'Participant_Contribution_Candidate_Payment_01.payment_instrument_id_label',
              'dataType' => 'Integer',
              'label' => E::ts('Payment Method'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'Participant_Contribution_Candidate_Payment_01.check_number',
              'dataType' => 'String',
              'label' => E::ts('Check#'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'Participant_Webform.Candidate_Representative_Name',
              'dataType' => 'String',
              'label' => E::ts('Paid By'),
              'sortable' => TRUE,
              'rewrite' => '{if "[Participant_Webform.Candidate_Representative_Name]"}[Participant_Webform.Candidate_Representative_Name]{else}[contact_id.sort_name]{/if}',
            ],
            [
              'type' => 'field',
              'key' => 'Participant_Contribution_Candidate_Payment_01.total_amount',
              'dataType' => 'Money',
              'label' => E::ts('Total Payable'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'SUM_Participant_Contribution_Candidate_Payment_01_Contribution_EntityFinancialTrxn_FinancialTrxn_01_amount',
              'dataType' => 'Money',
              'label' => E::ts('Total External Fees'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'id',
              'dataType' => 'Integer',
              'label' => E::ts('Cand#'),
              'sortable' => TRUE,
            ],
          ],
          'actions' => TRUE,
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
