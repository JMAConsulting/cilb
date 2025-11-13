<?php

namespace Drupal\skinr_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Select;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class SkinEditForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    // Override save button label.
    if ($this->entity->isNew()) {
      $actions['submit']['#value'] = $this->t('Add');
    }

    return $actions;
  }

  /**
   * Handles switching the available elements based on the selected theme and element type.
   */
  public function updateElement($form, FormStateInterface $form_state) {
    $theme_name = $form_state->getValue('theme');
    $element_type = $form_state->getValue('element_type');

    $form['element']['#options'] = self::elementOptions($theme_name, $element_type);
    Select::processSelect($form['element'], $form_state, $form);

    return $form['element'];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var Skin $skin */
    $skin = $this->entity;

    $theme_handler = \Drupal::service('theme_handler');

    if ($skin->isNew()) {
      $themes = $theme_handler->listInfo();
      $form['theme'] = [
        '#type' => 'select',
        '#title' => t('Theme'),
        '#options' => array_map(function ($theme) {
          return $theme->info['name'];
        }, $themes),
        '#default_value' => $form_state->getValue('theme'),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::updateElement',
          'wrapper' => 'dropdown-element-replace',
        ],
      ];

      $form['element_type'] = [
        '#type' => 'select',
        '#title' => t('Type'),
        '#options' => skinr_get_config_info(),
        '#default_value' => $form_state->getValue('element_type'),
        '#required' => TRUE,
        '#ajax' => [
          'callback' => '::updateElement',
          'wrapper' => 'dropdown-element-replace',
        ],
      ];

      $form['element'] = [
        '#type' => 'select',
        '#title' => t('Element'),
        '#prefix' => '<div id="dropdown-element-replace">',
        '#suffix' => '</div>',
        '#options' => self::elementOptions($form_state->getValue('theme'), $form_state->getValue('element_type')),
        '#required' => TRUE,
        // @todo States doesn't work with ajax.
        // @see https://www.drupal.org/node/1091852
        /*
        '#states' => array(
          'visible' => array(
            'select[name="theme"]' => array('filled' => TRUE),
            //'select[name="element_type"]' => array('empty' => FALSE),
          ),
        ),
        */
      ];

      $skin_infos = skinr_get_skin_info();
      // Apply overridden status to skins.
      foreach ($skin_infos as $skin_name => $skin_info) {
        $skin_infos[$skin_name]['status'] = skinr_skin_info_status_get($skin_infos[$skin_name]);
      }
      // @todo Only display enabled skins.
      // @todo Group by groups.
      $form['skin'] = [
        '#type' => 'select',
        '#title' => t('Skin'),
        '#options' => array_map(function ($skin_info) {
            return $skin_info['title'];
        }, $skin_infos),
        '#required' => TRUE,
        // @todo States doesn't work with ajax.
        // @see https://www.drupal.org/node/1091852
        /*
        '#states' => array(
          'visible' => array(
            'select[name="element"]' => array('empty' => FALSE),
          ),
        ),
        */
      ];
    }
    else {
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

      $form['info']['theme_info'] = [
        '#type' => 'item',
        '#title' => t('Theme'),
        '#markup' => $skin->themeLabel(),
      ];

      $form['info']['skin_info'] = [
        '#type' => 'item',
        '#title' => t('Skin'),
        '#markup' => $skin->skinLabel(),
      ];

      $form['element_type'] = [
        '#type' => 'value',
        '#value' => $skin->element_type,
      ];
      $form['element'] = [
        '#type' => 'value',
        '#value' => $skin->element,
      ];
      $form['theme'] = [
        '#type' => 'value',
        '#value' => $skin->theme,
      ];
      $form['skin'] = [
        '#type' => 'value',
        '#value' => $skin->skin,
      ];

      $skin_infos = skinr_get_skin_info();
      // Add custom info.
      $skin_infos['_additional'] = [
        'title' => t('Additional'),
      ];
      $skin_info = $skin_infos[$skin->skin];

      // Create options widget.
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
            'theme' => $skin->theme,
            'skin_name' => $skin->skin,
            'skin_info' => $skin_info,
            'value' => $defaults[$skin->theme][$skin->skin] ?? [],
          ];
          $field = $skin_info['form callback']($form, $form_state, $context);
        }
      }
      // @todo Can we turn this into a callback instead so we don't need the exception?
      elseif ($skin->skin == '_additional') {
        $field = [
          '#type' => 'textfield',
          '#title' => t('CSS classes'),
          '#size' => 40,
          '#description' => t('To add CSS classes manually, enter classes separated by a single space i.e. <code>first-class second-class</code>'),
          '#default_value' => $skin->getOptions(),
        ];
        if (skinr_ui_access('edit advanced skin settings')) {
          $field['#disabled'] = TRUE;
          $field['#description'] .= '<br /><em>' . t('You require additional permissions to edit this setting.') . '</em>';
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
              '#default_value' => $skin->getOptions(),
              '#description' => $skin_info['description'],
              '#weight' => $skin_info['weight'] ?? NULL,
            ];
            break;

          case 'radios':
            $field = [
              '#type' => 'radios',
              '#title' => $skin_info['title'],
              '#options' => array_merge(['' => '&lt;none&gt;'], $this->optionsToFormOptions($skin_info['options'])),
              '#default_value' => $skin->getOptions(),
              '#description' => $skin_info['description'],
              '#weight' => $skin_info['weight'] ?? NULL,
            ];
            break;

          case 'select':
            $field = [
              '#type' => 'select',
              '#title' => $skin_info['title'],
              '#options' => array_merge(['' => '<none>'], $this->optionsToFormOptions($skin_info['options'])),
              '#default_value' => $skin->getOptions(),
              '#description' => $skin_info['description'],
              '#weight' => $skin_info['weight'] ?? NULL,
            ];
            break;

          default:
            // Raise an error.
            $this->messenger()->addError(t("Widget %name's type is invalid.", ['%name' => $skin->skin]), FALSE);
            break;
        }
      }
      $form['options'] = $field;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    $destination = \Drupal::service('redirect.destination')->getAsArray();
    if ($destination['destination'] == base_path() . 'admin/structure/skinr/add') {
      $form_state->setRedirect('entity.skin.edit_form', ['skin' => $this->entity->id()]);
    }
    elseif ($destination['destination'] == base_path() . 'admin/structure/skinr/' . $this->entity->id() . '/edit') {
      $form_state->setRedirect('skinr_ui.list');
    }

    return $status;
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
   * Return an array of element options for a module.
   *
   * If no field type is provided, returns a nested array of all element options,
   * keyed by module.
   *
   * @param string $theme_name
   * @param string $element_type
   *
   * @return array
   */
  protected function elementOptions($theme_name = NULL, $element_type = NULL) {
    $options = &drupal_static(__FUNCTION__);

    if (!isset($options)) {
      $options = skinr_invoke_all('skinr_ui_element_options', $theme_name);
    }

    if ($element_type && isset($options[$element_type])) {
      if (!empty($theme_name)) {
        $theme = \Drupal::service('theme_handler')->getTheme($theme_name);
        $theme_label = $theme->info['name'];

        if (isset($options[$element_type][$theme_label])) {
          return $options[$element_type][$theme_label];
        }
      }
      return $options[$element_type];
    }

    return [];
  }

}
