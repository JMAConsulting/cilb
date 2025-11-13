<?php

/**
 * @file
 */

/**
 * Implements hook_skinr_skin_info().
 */
function skinr_test_basetheme_skinr_skin_info() {
  $skins['skinr_test_basetheme'] = [
    'title' => 'Base theme skin',
    'default status' => 0,
    'status' => [
      'skinr_test_basetheme' => 1,
    ],
  ];
  return $skins;
}
