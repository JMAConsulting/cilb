<?php

namespace Drupal\redirect_page_by_role\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Define a form to configure Redirect Page By Role module settings.
 */
class RedirectPageByRoleSettingsForm extends ConfigFormBase {

  /**
   * A storage instance.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManagerInterface.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);

    $this->entityStorage = $entity_type_manager->getStorage('node_type');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'redirect_page_by_role.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'redirect_page_by_role_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('redirect_page_by_role.settings');
    $roles = user_roles();
    $node_types = $this->entityStorage->loadMultiple();
    $node_types_header = [
      $this->t('Role'),
      $this->t('Skip bundle rule'),
      $this->t('Redirect to'),
      $this->t('Weight'),
    ];

    $form['roles']['bypass_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Role to bypass the redirection rules.'),
      '#default_value' => $config->get('bypass_roles') ?? [],
      '#options' => array_map(function ($role) {
        return $role->label();
      }, $roles),
      '#description' => $this->t('The selected roles will not pass through the redirection rules.'),
    ];
    $form['default_status_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Default redirect status'),
      '#description' => $this->t('Define a default status for redirection'),
      '#options' => redirect_page_by_role_status_code_options(),
      '#default_value' => $config->get('default_status_code') ?? 302,
    ];
    $form['content_types'] = [
      '#type' => 'fieldset',
      '#title' => 'Content types default settings',
      '#tree' => TRUE,
    ];

    foreach ($node_types as $type => $bundle) {
      $class_group = $type . '-weight';

      $form['content_types'][$type] = [
        '#type' => 'details',
        '#title' => $bundle->label(),
        '#tree' => TRUE,
      ];
      $form['content_types'][$type]['roles'] = [
        '#type' => 'table',
        '#header' => $node_types_header,
        '#tree' => TRUE,
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => $class_group,
          ],
        ],
      ];
      foreach ($roles as $id => $role) {
        $form['content_types'][$type]['roles'][$id] = [
          'label' => [
            '#plain_text' => $role->label(),
          ],
          'skip_rule' => [
            '#type' => 'checkbox',
            '#default_value' => $config->get($type)[$id]["skip_rule"] ?? '',
          ],
          'redirect_to' => [
            '#type' => 'textfield',
            '#default_value' => $config->get($type)[$id]["redirect_to"] ?? '',
          ],
          'weight' => [
            '#type' => 'weight',
            '#default_value' => $config->get($type)[$id]['weight'] ?? 0,
            '#attributes' => ['class' => [$class_group]],
          ],
        ];
        $form['content_types'][$type]['roles'][$id]['#weight'] = $config->get($type)[$id]['weight'] ?? 0;
        $form['content_types'][$type]['roles'][$id]['#attributes']['class'][] = 'draggable';
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('redirect_page_by_role.settings');
    $content_type_rules = $form_state->getValue('content_types');
    $bypass_roles = array_filter($form_state->getValue('bypass_roles'));
    $hasRules = [];

    foreach ($content_type_rules as $type => $bundle) {
      foreach ($bundle['roles'] as $role => $rule) {
        $bundle['roles'][$role]['redirect_to'] = trim($rule['redirect_to']);
        if (!empty($bundle["roles"][$role]["redirect_to"]) && !in_array($type . '_general', $hasRules)) {
          $hasRules[] = $type . '_general';
        }
        elseif (empty($bundle["roles"][$role]["redirect_to"])) {
          unset($bundle['roles'][$role]['redirect_to']);
        }
      }
      $role_rules = array_filter($bundle['roles']);
      if ($role_rules) {
        $config->set($type, $role_rules);
      }
      else {
        $config->clear($type);
      }
    }

    $config->set('hasRules', $hasRules)
      ->set('default_status_code', $form_state->getValue('default_status_code'))
      ->set('bypass_roles', $bypass_roles)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
