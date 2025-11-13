<?php

namespace Drupal\skinr_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\skinr\Entity\Skin;

/**
 *
 */
class SkinsEditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'skins_edit_form';
  }

  /**
   * Returns a page title.
   *
   * @param string $element_type
   *   The current element's type.
   * @param string $element
   *   The current element.
   * @param string $theme
   *   The theme this.
   *
   * @return string
   */
  public function getTitle($element_type = NULL, $element = NULL, $theme = NULL) {
    $skin = Skin::create([
      'element_type' => $element_type,
      'element' => $element,
      'theme' => $theme,
    ]);
    return t('Skin settings for !element !element_type', ['!element_type' => strtolower($skin->elementTypeLabel()), '!element' => $skin->elementLabel()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $element_type = NULL, $element = NULL, $theme = NULL) {
    if ($theme === NULL) {
      $theme = \Drupal::theme()->getActiveTheme()->getName();
    }

    $theme_handler = \Drupal::service('theme_handler');
    /** @var \Drupal\Core\Extension\Extension[]|\stdClass[] $themes */
    $themes = $theme_handler->listInfo();
    ksort($themes);

    $form['current_theme'] = [
      '#type' => 'value',
      '#value' => $theme,
    ];

    $defaults = [];
    if ($form_state->hasValue('skinr_settings')) {
      $defaults = $form_state->getValue('skinr_settings');
    }
    else {
      foreach ($themes as $section_theme) {
        if (!$section_theme->status) {
          continue;
        }

        $section_theme_name = $section_theme->getName();

        $properties = [
          'element_type' => $element_type,
          'element' => $element,
          'theme' => $section_theme_name,
        ];
        /** @var \Drupal\skinr\Entity\Skin[] $skins */
        $skins = \Drupal::service('entity_type.manager')
          ->getStorage('skin')
          ->loadByProperties($properties);
        foreach ($skins as $skin) {
          $defaults[$section_theme_name][$skin->skin] = $skin->getOptions();
        }
      }

      // Set default values.
      $form_state->setValue('skinr_settings', $defaults);
    }

    // Display info.
    $skin = Skin::create([
      'element_type' => $element_type,
      'element' => $element,
      'theme' => $theme,
    ]);
    $form['info']['element_type_info'] = [
      '#type' => 'item',
      '#title' => t('Type'),
      '#markup' => $skin->elementTypeLabel(),
    ];
    $form['info']['element_info'] = [
      '#type' => 'item',
      '#title' => t('Element'),
      '#markup' => $skin->elementLabel(),
    ];

    // Set form class.
    $form['#attributes'] = ['class' => ['skinr-form']];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 50,
    ];

    $form['skinr']['element_type'] = [
      '#type' => 'hidden',
      '#value' => $element_type,
    ];
    $form['skinr']['element'] = [
      '#type' => 'hidden',
      '#value' => $element,
    ];

    $groups = skinr_get_group_info();
    $skin_infos = skinr_get_skin_info();

    // Apply overridden status to skins.
    foreach ($skin_infos as $skin_name => $skin_info) {
      $skin_infos[$skin_name]['status'] = skinr_skin_info_status_get($skin_infos[$skin_name]);
    }

    // Invoke hook_skinr_theme_hooks() and hook_skinr_theme_hooks_alter().
    $theme_hooks = skinr_theme_hooks($element_type, $element);

    $form['skinr_settings'] = [
      '#tree' => TRUE,
      '#type' => 'container',
    ];

    foreach ($themes as $section_theme) {
      if (!$section_theme->status) {
        continue;
      }

      $section_theme_name = $section_theme->getName();

      // If this hook is a region, and the region does not exist for this
      // theme, don't bother outputting any of the settings.
      if (!empty($section_theme_hooks) && strpos($theme_hooks[0], 'region') === 0) {
        // Strip the region__ part off the region name.
        $region = substr($theme_hooks[0], 8);

        $regions = system_region_list($section_theme_name, REGIONS_VISIBLE);
        if (!isset($regions[$region])) {
          continue;
        }
      }

      // Build theme sub-form.
      $form['skinr_settings'][$section_theme_name] = [
        '#type' => 'details',
        '#title' => $section_theme->info['name'] . ($section_theme_name == $section_theme ? ' (' . t('enabled + default') . ')' : ''),
        '#open' => $section_theme_name == $theme ? TRUE : FALSE,
      ];
      if ($section_theme_name == $theme) {
        // Current theme goes at the top.
        $form['skinr_settings'][$section_theme_name]['#attributes'] = ['class' => ['skinr-ui-current-theme']];
        $form['skinr_settings'][$section_theme_name]['#weight'] = -10;
      }

      // Use vertical tabs.
      $form['skinr_settings'][$section_theme_name]['groups'] = [
        '#type' => 'vertical_tabs',
      ];

      // Create individual widgets for each skin.
      foreach ($skin_infos as $skin_name => $skin_info) {
        // Check if this skin is disabled.
        if (empty($skin_info['status'][$section_theme_name])) {
          continue;
        }

        // Check if this skin applies to this hook.
        if (!is_array($skin_info['theme hooks']) || (!in_array('*', $skin_info['theme hooks']) && !$this->isFeatured($theme_hooks, $skin_info['theme hooks']))) {
          continue;
        }

        // Create widget.
        $field = [];
        if (!empty($skin_info['form callback'])) {
          // Process custom form callbacks.
          // Load include file.
          if (!empty($skin_info['source']['include file'])) {
            skinr_load_include($skin_info['source']['include file']);
          }

          // Execute form callback.
          if (function_exists($skin_info['form callback'])) {
            $context = [
              'theme' => $section_theme_name,
              'skin_name' => $skin_name,
              'skin_info' => $skin_info,
              'value' => $defaults[$section_theme_name][$skin_name] ?? [],
            ];
            $field = $skin_info['form callback']($form, $form_state, $context);
          }
        }
        else {
          switch ($skin_info['type']) {
            case 'checkboxes':
              $field = [
                '#type' => 'checkboxes',
                '#multiple' => TRUE,
                '#title' => $skin_info['title'],
                '#options' => $this->optionsToFormOptions($skin_info['options']),
                '#default_value' => $defaults[$section_theme_name][$skin_name] ?? [],
                '#description' => $skin_info['description'],
                '#weight' => $skin_info['weight'] ?? NULL,
              ];
              break;

            case 'radios':
              $field = [
                '#type' => 'radios',
                '#title' => $skin_info['title'],
                '#options' => array_merge(['' => '&lt;none&gt;'], $this->optionsToFormOptions($skin_info['options'])),
                '#default_value' => $defaults[$section_theme_name][$skin_name] ?? '',
                '#description' => $skin_info['description'],
                '#weight' => $skin_info['weight'] ?? NULL,
              ];
              break;

            case 'select':
              $field = [
                '#type' => 'select',
                '#title' => $skin_info['title'],
                '#options' => array_merge(['' => '<none>'], $this->optionsToFormOptions($skin_info['options'])),
                '#default_value' => $defaults[$section_theme_name][$skin_name] ?? '',
                '#description' => $skin_info['description'],
                '#weight' => $skin_info['weight'] ?? NULL,
              ];
              break;

            default:
              // Raise an error.
              $this->messenger()->addError(t("Widget %name's type is invalid.", ['%name' => $skin_name]), FALSE);
              break;
          }
        }
        if (empty($skin_info['group']) || empty($groups[$skin_info['group']])) {
          $form['skinr_settings'][$section_theme_name][$skin_name] = $field;
        }
        else {
          if (!empty($field) && !isset($form['skinr_settings'][$section_theme_name]['groups'][$skin_info['group']])) {
            $group = $groups[$skin_info['group']];
            $form['skinr_settings'][$section_theme_name]['groups'][$skin_info['group']] = [
              '#type' => 'details',
              '#title' => $group['title'],
              '#description' => $group['description'],
              '#group' => 'skinr_settings][' . $section_theme_name . '][groups',
              '#weight' => $group['weight'] ?? NULL,
            ];
          }
          $form['skinr_settings'][$section_theme_name]['groups'][$skin_info['group']][$skin_name] = $field;
        }
      }

      // Check for access.
      if (skinr_ui_access('edit advanced skin settings')) {
        $skin_name = '_additional';
        $form['skinr_settings'][$section_theme_name]['groups']['_additional'] = [
          '#type' => 'details',
          '#title' => t('Advanced'),
          '#group' => 'skinr_settings][' . $section_theme_name . '][groups',
          '#weight' => 50,
        ];
        $form['skinr_settings'][$section_theme_name]['groups']['_additional']['_additional'] = [
          '#type' => 'textfield',
          '#title' => t('CSS classes'),
          '#size' => 40,
          '#description' => t('To add CSS classes manually, enter classes separated by a single space i.e. <code>first-class second-class</code>'),
          '#default_value' => $defaults[$section_theme_name][$skin_name] ?? '',
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $element_type = $form_state->getValue('element_type');
    $element = $form_state->getValue('element');

    $error = FALSE;
    if ($form_state->hasValue('skinr_settings')) {
      foreach ($form_state->getValue('skinr_settings') as $section_theme_name => $section_theme) {
        if (isset($section_theme['groups']['_additional']['_additional'])) {
          // Validate additional classes field.
          if (preg_match('/[^a-zA-Z0-9\-\_\s]/', $section_theme['groups']['_additional']['_additional'])) {
            $form_state->setErrorByName('skinr_settings][' . $section_theme_name . '][groups][_additional][_additional', t('Additional classes for Skinr may only contain alphanumeric characters, spaces, - and _.'));
            $error = TRUE;
          }
        }
      }
    }

    if (!$error) {
      $groups = skinr_get_group_info();
      // Add hard-coded additional classes group.
      $groups['_additional'] = [
        'title' => t('Additional'),
        'description' => t('Additional custom classes.'),
        'weight' => 0,
      ];

      if ($form_state->hasValue('skinr_settings')) {
        $skinr_settings = $form_state->getValue('skinr_settings');
        foreach ($skinr_settings as $section_theme_name => $section_theme) {
          // Unset active tab variables.
          foreach ($section_theme['groups'] as $skin_name => $options) {
            if (strpos($skin_name, '__groups__active_tab') !== FALSE) {
              unset($skinr_settings[$section_theme_name]['groups'][$skin_name]);
              continue;
            }
          }
          // Undo any grouping to ease processing on submit.
          foreach ($groups as $group_name => $group) {
            if (!empty($section_theme['groups'][$group_name]) && is_array($section_theme['groups'][$group_name])) {
              $group_values = $section_theme['groups'][$group_name];
              unset($skinr_settings[$section_theme_name]['groups'][$group_name]);
              $skinr_settings[$section_theme_name] = array_merge($skinr_settings[$section_theme_name], $group_values);
            }
          }
          unset($skinr_settings[$section_theme_name]['groups']);
        }
        $form_state->setValue('skinr_settings', $skinr_settings);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $element_type = $form_state->getValue('element_type');
    $element = $form_state->getValue('element');

    if ($form_state->hasValue('skinr_settings')) {
      foreach ($form_state->getValue('skinr_settings') as $section_theme_name => $section_theme) {
        // Process widgets.
        if (!empty($section_theme) && is_array($section_theme)) {
          foreach ($section_theme as $skin_name => $options) {
            if ($skin_name == '_additional' && !\Drupal::currentUser()->hasPermission('edit advanced skin settings')) {
              // This user doesn't have access to alter these options.
              continue;
            }

            // Ensure options is an array.
            if (!is_array($options)) {
              $options = $skin_name == '_additional' ? explode(' ', $options) : [$options];
            }
            // Sanitize options.
            $options = _skinr_array_strip_empty($options);

            // Find existing skin.
            unset($skin);
            $properties = [
              'theme' => $section_theme_name,
              'element_type' => $element_type,
              'element' => $element,
              'skin' => $skin_name,
            ];
            /** @var \Drupal\skinr\Entity\Skin[] $skins */
            $skins = \Drupal::service('entity_type.manager')
              ->getStorage('skin')
              ->loadByProperties($properties);
            if ($skins) {
              $skin = reset($skins);
            }

            if (empty($options)) {
              if (!empty($skin)) {
                // Delete this skin configuration.
                $skin->delete();
              }
              continue;
            }

            if (empty($skin)) {
              // It doesn't exist, so create a new skin.
              $skin = Skin::create([
                'element_type' => $element_type,
                'element' => $element,
                'theme' => $section_theme_name,
                'skin' => $skin_name,
              ]);
            }
            $skin->setOptions($options);
            $skin->enable();

            // Save skin.
            $skin->save();
          }
        }
      }
    }
  }

  /**
   * Helper function to convert an array of options, as specified in the .info
   * file, into an array usable by Form API.
   *
   * @param $options
   *   An array containing at least the 'class' and 'label' keys.
   *
   * @return string[]
   *   A Form API compatible array of options.
   *
   * @todo Rename function to be more descriptive.
   */
  protected function optionsToFormOptions($options) {
    $form_options = [];
    foreach ($options as $option_name => $option) {
      $form_options[$option_name] = $option['title'];
    }
    return $form_options;
  }

  /**
   * Helper function to determine whether one of a set of hooks exists in a list
   * of required theme hooks.
   *
   * @param $theme_hooks
   *   An array of theme hooks available to this element.
   * @param $allowed_hooks
   *   An array of allowed theme hooks.
   *
   * @return bool
   *   TRUE if an overlap is found, FALSE otherwise.
   *
   * @todo Rename function to be more descriptive.
   */
  protected function isFeatured($theme_hooks, $allowed_hooks) {
    foreach ($theme_hooks as $theme_hook) {
      if (in_array($theme_hook, $allowed_hooks)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
