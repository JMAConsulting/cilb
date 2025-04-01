<?php

/**
 * @file
 */

/**
 * Implements hook_skinr_api_VERSION().
 */
function skinr_test_skinr_api_3() {
  return [
    'directory' => 'skins',
  ];
}

/**
 * Implements hook_skinr_group_info().
 */
function skinr_test_skinr_group_info() {
  $groups['skinr_test'] = [
    'title' => t('skinr_test'),
  ];
}

/**
 * Implements hook_skinr_skin_info().
 */
function skinr_test_skinr_skin_info() {
  $skins['skinr_test_font'] = [
    'title' => t('Font family'),
    'type' => 'select',
    'group' => 'typography',
    'theme hooks' => ['block', 'region'],
    'default status' => 1,
    'attached' => [
      'css' => [
        'skinr_test.css',
        'skinr_test_advanced.css' => ['group' => CSS_THEME, 'weight' => 999],
        ['data' => 'skinr_test_data.css', 'group' => CSS_THEME, 'weight' => 999],
      ],
      'js' => ['skinr_test.js'],
    ],
    'options' => [
      'font_1' => [
        'title' => 'Arial, Helvetica, Nimbus Sans L, Liberation Sans, FreeSans',
        'class' => ['font-1'],
      ],
      'font_2' => [
        'title' => 'Lucida Grande, Lucida Sans Unicode, DejaVu Sans, Tahoma',
        'class' => ['font-2'],
      ],
    ],
  ];
  $skins['skinr_test_invalid_class'] = [
    'title' => t('Invalid'),
    'type' => 'checkboxes',
    'group' => 'general',
    'theme hooks' => ['block'],
    'default status' => 1,
    'options' => [
      'invalid_class' => [
        'title' => 'Nothing',
        'class' => 'invalid-class',
      ],
    ],
  ];
  return $skins;
}
