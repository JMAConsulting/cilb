<?php
use CRM_Mjwshared_ExtensionUtil as E;

return [
  [
    'name' => 'SavedSearch_Paymentprocessor_Webhook_Detail',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Paymentprocessor_Webhook_Detail',
        'label' => E::ts('Paymentprocessor Webhook Detail'),
        'api_entity' => 'PaymentprocessorWebhook',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'data',
            'identifier',
            'message',
            'trigger',
            'status',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [],
          'having' => [],
        ],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'SavedSearch_Paymentprocessor_Webhook_Detail_SearchDisplay_Paymentprocessor_Webhook_Detail',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Paymentprocessor_Webhook_Detail',
        'label' => E::ts('Paymentprocessor Webhook Detail'),
        'saved_search_id.name' => 'Paymentprocessor_Webhook_Detail',
        'type' => 'list',
        'settings' => [
          'style' => 'ul',
          'limit' => 1,
          'sort' => [],
          'pager' => FALSE,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'id',
              'dataType' => 'Integer',
              'label' => E::ts('ID'),
            ],
            [
              'type' => 'field',
              'key' => 'identifier',
              'dataType' => 'String',
              'label' => E::ts('Identifier'),
            ],
            [
              'type' => 'field',
              'key' => 'message',
              'dataType' => 'String',
              'break' => FALSE,
              'label' => E::ts('Message'),
            ],
            [
              'type' => 'field',
              'key' => 'trigger',
              'dataType' => 'String',
              'label' => E::ts('Trigger'),
            ],
            [
              'type' => 'field',
              'key' => 'status',
              'dataType' => 'String',
              'label' => E::ts('Status'),
            ],
            [
              'type' => 'html',
              'key' => 'data',
              'dataType' => 'Text',
              'label' => E::ts('Data'),
              'cssRules' => [
                ['code-formatted'],
              ],
            ],
          ],
          'placeholder' => 0,
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
