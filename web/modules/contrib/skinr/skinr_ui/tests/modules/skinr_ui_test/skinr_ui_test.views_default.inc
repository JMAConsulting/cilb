<?php

/**
 * @file
 * Default views.
 */

/**
 * Implementation of hook_views_default_views().
 */
function skinr_ui_test_views_default_views() {
  static $views;

  if (isset($views)) {
    return $views;
  }

  $files = \Drupal::service('file_system')->scanDirectory(\Drupal::service('extension.path.resolver')->getPath('module', 'skinr_ui_test') . '/views_default', '/\.inc$/');
  foreach ($files as $filepath => $file) {
    include $filepath;
    if (isset($view)) {
      $views[$view->name] = $view;
    }
  }
  return $views;
}
