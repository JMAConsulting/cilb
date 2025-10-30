<?php

/**
 * @file
 * Provide views data for Redirect Page by Role module.
 *
 * @ingroup views_module_handlers
 */

/**
 * Implements hook_views_data().
 */
function redirect_page_by_role_views_data() {
  $data = [];
  $data['redirect_page_by_role_node'] = [];

  $data['redirect_page_by_role_node']['table'] = [
    'group' => t('Redirect Page By Role'),
    'provider' => 'redirect_page_by_role',
    'base' => [
      'field' => 'nid',
      'title' => t('Redirect Page By Role'),
      'help' => t('Redirect Page By Role custom table'),
    ],
  ];

  $data['redirect_page_by_role_node']['nid'] = [
    'title' => t('Node'),
    'help' => t('Node with redirection rule.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['redirect_page_by_role_node']['role'] = [
    'title' => t('Role'),
    'help' => t('User role to be redirected.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['redirect_page_by_role_node']['redirect_to'] = [
    'title' => t('Redirect to'),
    'help' => t('Where the user will be redirected.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['redirect_page_by_role_node']['status_code'] = [
    'title' => t('Status code'),
    'help' => t('Status code that the user will be redirected.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  $data['redirect_page_by_role_node']['weight'] = [
    'title' => t('Weight'),
    'help' => t('Weight of the rule.'),
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];

  return $data;
}
