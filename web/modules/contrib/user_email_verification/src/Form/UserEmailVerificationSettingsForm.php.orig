<?php

namespace Drupal\user_email_verification\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Configures user email verification this site.
 */
class UserEmailVerificationSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_email_verification_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['user_email_verification.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('user_email_verification.settings');
    $roles = user_role_names(TRUE);
    unset($roles[UserInterface::AUTHENTICATED_ROLE]);

    $form['skip_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Skip roles'),
      '#default_value' => $config->get('skip_roles'),
      '#options' => $roles,
      '#description' => $this->t('Select the roles for which we should not verify the Email address.'),
    ];

    $form['validate_interval'] = [
      '#type' => 'number',
      '#min' => 1,
      '#step' => 1,
      '#title' => $this->t('Verification time interval'),
      '#default_value' => $config->get('validate_interval'),
      '#field_suffix' => $this->t('seconds'),
      '#description' => $this->t('Enter the time interval in seconds in which the user must validate Email.'),
      '#required' => TRUE,
    ];

    $form['num_reminders'] = [
      '#type' => 'select',
      '#title' => $this->t('Send reminder'),
      '#options' => [
        0 => $this->t('- Never -'),
        1 => $this->t('Once'),
        2 => $this->t('Twice'),
        3 => $this->t('Three times'),
      ],
      '#default_value' => $config->get('num_reminders'),
      '#description' => $this->t('Select the number of reminders to be sent spread equally through the time interval in which the user must validate Email.'),
    ];

    $form['mail_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification mail subject'),
      '#default_value' => $config->get('mail_subject'),
      '#maxlength' => 180,
      '#description' => $this->t('Subject for Email when user is requesting a new verification link or Verify your Email reminder mail.'),
      '#states' => [
        'visible' => [
          ['select[name="num_reminders"]' => ['value' => '1']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '2']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '3']],
        ],
        'required' => [
          ['select[name="num_reminders"]' => ['value' => '1']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '2']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '3']],
        ],
      ],
    ];

    $form['mail_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Verification mail body'),
      '#default_value' => $config->get('mail_body'),
      '#description' => $this->t('Use [user:verify-email] to display the link to Email verification.'),
      '#rows' => 5,
      '#states' => [
        'visible' => [
          ['select[name="num_reminders"]' => ['value' => '1']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '2']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '3']],
        ],
        'required' => [
          ['select[name="num_reminders"]' => ['value' => '1']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '2']],
          'or',
          ['select[name="num_reminders"]' => ['value' => '3']],
        ],
      ],
    ];

    $form['extended_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable extended verification period'),
      '#default_value' => $config->get('extended_enable'),
      '#description' => $this->t('Extended verification period allows you to define another time period when the account can be still verified even after being blocked.'),
    ];

    $form['extended'] = [
      '#type' => 'details',
      '#title' => $this->t('Extended verification period'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          'input[name="extended_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['extended']['extended_validate_interval'] = [
      '#type' => 'number',
      '#min' => 1,
      '#step' => 1,
      '#title' => $this->t('Extended verification time interval'),
      '#default_value' => $config->get('extended_validate_interval'),
      '#field_suffix' => $this->t('seconds'),
      '#description' => $this->t('Enter the extended time interval in seconds (the time after "Verification time interval") in which the user must validate Email before the account gets deleted completely.'),
      '#states' => [
        'required' => [
          'input[name="extended_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['extended']['extended_mail_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mail subject'),
      '#default_value' => $config->get('extended_mail_subject'),
      '#maxlength' => 180,
      '#description' => $this->t('Subject for Email when an account is blocked after not being verified.'),
      '#states' => [
        'required' => [
          'input[name="extended_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['extended']['extended_mail_body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mail body'),
      '#default_value' => $config->get('extended_mail_body'),
      '#rows' => 5,
      '#description' => $this->t('Use [user:verify-email-extended] to display the link to Email verification.'),
      '#states' => [
        'required' => [
          'input[name="extended_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['extended']['extended_end_delete_account'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete user account when "Extended verification time interval" ended'),
      '#default_value' => $config->get('extended_end_delete_account'),
      '#description' => $this->t(
        'To choose the logic of user account delete visit <a href="@href" target="_blank">Account settings page</a> and setup it using <strong>When cancelling a user account</strong> item.',
        ['@href' => Url::fromRoute('entity.user.admin_form')->toString()]
      ),
    ];

    $form['no_creation_auto_verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable auto verification on account creation'),
      '#default_value' => $config->get('no_creation_auto_verify'),
      '#description' => $this->t('Disable email auto verification for accounts which were created by the users with "administer users" permission.'),
    ];

    $form['no_unblock_auto_verify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable auto verification of blocked accounts on activation'),
      '#default_value' => $config->get('no_unblock_auto_verify'),
      '#description' => $this->t('Disable email auto verification when the user with "administer users" permission activates blocked user account.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('user_email_verification.settings')
      ->set('skip_roles', array_filter($form_state->getValue('skip_roles')))
      ->set('no_creation_auto_verify', (bool) $form_state->getValue('no_creation_auto_verify'))
      ->set('no_unblock_auto_verify', (bool) $form_state->getValue('no_unblock_auto_verify'))
      ->set('validate_interval', (int) $form_state->getValue('validate_interval'))
      ->set('num_reminders', (int) $form_state->getValue('num_reminders'))
      ->set('mail_subject', trim($form_state->getValue('mail_subject')))
      ->set('mail_body', trim($form_state->getValue('mail_body')))
      ->set('extended_enable', (bool) $form_state->getValue('extended_enable'))
      ->set('extended_validate_interval', (int) $form_state->getValue('extended_validate_interval'))
      ->set('extended_mail_subject', trim($form_state->getValue('extended_mail_subject')))
      ->set('extended_mail_body', trim($form_state->getValue('extended_mail_body')))
      ->set('extended_end_delete_account', (bool) $form_state->getValue('extended_end_delete_account'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
