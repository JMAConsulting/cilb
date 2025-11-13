<?php

/**
 * @file
 */

/**
 * Implements hook_skinr_skin_info().
 */
function skinr_test_subtheme_other_skinr_skin_info() {
  $skins['skinr_test_subtheme_other'] = [
    'title' => 'Other subtheme skin',
    'default status' => 1,
  ];
  return $skins;
}
