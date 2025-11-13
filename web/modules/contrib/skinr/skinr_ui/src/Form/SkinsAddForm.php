<?php

namespace Drupal\skinr_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class SkinsAddForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $skin = $this->entity;

    $form['type'] = [
      '#type' => 'select',
      '#title' => t('Type'),
      '#options' => skinr_get_config_info(),
      '#required' => TRUE,
    ];

    $element_options = $this->elementOptions();
    $form['element'] = [
      '#type' => 'select',
      '#title' => t('Element'),
      '#options' => $element_options,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Add'),
    ];

    // @todo
    /*
    $form['#attached']['js'][] = \Drupal::service('extension.path.resolver')->getPath('module', 'skinr_ui') . '/js/skinr_ui.js';

    // Add settings for the update selects behavior.
    $form['#attached']['js'][] = array(
    'type' => 'setting',
    'data' => array('elementOptions' => $this->elementOptions()),
    );
     */

    return $form;
  }

  /**
   * Return an array of element options for a module.
   *
   * If no field type is provided, returns a nested array of all element options,
   * keyed by module.
   */
  protected function elementOptions($module = NULL) {
    $cache = &drupal_static(__FUNCTION__);

    if (!isset($cache)) {
      $config = skinr_get_config_info();
      $options = skinr_invoke_all('skinr_ui_element_options');

      foreach ($options as $type => $data) {
        $cache[$config[$type]] = $data;
      }
    }

    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $element_type = $form_state->getValue('type');
    $element = $form_state->getValue('element');
    $url = new Url('entity.skin.edit.' . $element_type, ['element_type' => $element_type, 'element' => $element]);

    $destination = \Drupal::service('redirect.destination')->getAsArray();
    if ($destination['destination'] == 'admin/structure/skinr/add') {
      $destination['destination'] = 'admin/structure/skinr';
    }
    $url->setOption('query', $destination);
    $form_state->setRedirectUrl($url);
  }

}
