<?php

/**
 * @file
 * Default pages.
 */

/**
 * Implementation of hook_default_page_manager_pages().
 */
function skinr_panels_test_default_page_manager_pages() {
  static $pages;

  if (isset($pages)) {
    return $pages;
  }

  $files = \Drupal::service('file_system')->scanDirectory(\Drupal::service('extension.path.resolver')->getPath('module', 'skinr_panels_test') . '/pages_default', '/\.inc$/');
  foreach ($files as $filepath => $file) {
    include $filepath;
    if (isset($page)) {
      $pages[$page->name] = $page;
    }
  }
  return $pages;
}
