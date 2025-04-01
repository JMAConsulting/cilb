<?php

/**
 * @file
 * Implements Skinr hooks for comment.module.
 */

/**
 * Implements hook_skinr_config_info().
 */
function comment_skinr_config_info() {
  return ['comment' => t('Comment')];
}

/**
 * Implements hook_skinr_ui_element_options().
 */
function comment_skinr_ui_element_options($theme_name = NULL) {
  $options = ['comment' => []];
  return $options;


  $types = \Drupal\node\Entity\NodeType::loadMultiple();
  foreach ($types as $type) {
    $options['comment'][$type->id()] = $type->label();
  }
  asort($options['comment']);

  return $options;
}

/**
 * Implements hook_skinr_ui_element_title().
 */
function comment_skinr_ui_element_title($module, $element, $theme_name) {
  if ($module == 'comment') {
    $type = node_type_get_type($element);
    return $type->name;
  }
}

/**
 * Implements hook_skinr_theme_hooks().
 */
function comment_skinr_theme_hooks($module, $element) {
  $theme_hooks = [];

  if ($module == 'comment') {
    $theme_hooks = [
      'comment_wrapper__' . $element,
      'comment_wrapper',
    ];
  }

  return $theme_hooks;
}

/**
 * Implements hook_skinr_elements().
 */
function comment_skinr_elements($variables, $hook) {
  $elements = [];
  if ($hook == 'comment_wrapper') {
    // dpm($variables['entity']->type);
    // dpm($variables['entity']->bundle());
    $elements['comment'] = [$variables['node']->id()];
  }
  return $elements;
}
