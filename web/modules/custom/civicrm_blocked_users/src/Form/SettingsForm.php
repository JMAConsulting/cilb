<?php

namespace Drupal\civicrm_blocked_users\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure CiviCRM Blocked Users settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['civicrm_blocked_users.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'civicrm_blocked_users_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('civicrm_blocked_users.settings');
    
    $form['civicrm_blocked_group'] = [
      '#type' => 'number',
      '#title' => $this->t('Blocked Users CiviCRM Group ID'),
      '#description' => $this->t('Enter the numeric ID of your "Blocked Users" group from CiviCRM. Find it at /civicrm/admin/group.'),
      '#default_value' => $config->get('group_id'),
      '#required' => TRUE,
      '#size' => 10,
      '#maxlength' => 10,
      '#min' => 1,
    ];

    $form['initial_sync'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Run initial sync now'),
      '#description' => $this->t('Add all currently blocked Drupal users (status=0) to the CiviCRM Blocked Users group.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('civicrm_blocked_users.settings')
      ->set('group_id', $form_state->getValue('civicrm_blocked_group'))
      ->save();

    if ($form_state->getValue('initial_sync')) {
      \Drupal::service('civicrm')->initialize();
      civicrm_blocked_users_initial_sync();
      $this->messenger()->addMessage($this->t('Initial sync completed!'));
    }

    parent::submitForm($form, $form_state);
  }

}

