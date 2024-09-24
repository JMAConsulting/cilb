<?php

namespace Drupal\user_email_verification\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserInterface;
use Drupal\user_email_verification\UserEmailVerificationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class UserEmailVerificationRequestForm.
 */
class UserEmailVerificationRequestForm extends FormBase {

  /**
   * User email verification helper service.
   *
   * @var \Drupal\user_email_verification\UserEmailVerificationInterface
   */
  protected $userEmailVerification;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The currently active request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The user.settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configUserSettings;

  /**
   * Constructs a new UserEmailVerificationRequestForm object.
   *
   * @param \Drupal\user_email_verification\UserEmailVerificationInterface $user_email_verification_service
   *   User email verification helper service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current active user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(UserEmailVerificationInterface $user_email_verification_service, AccountProxyInterface $current_user, RequestStack $request_stack, ConfigFactoryInterface $config_factory) {
    $this->userEmailVerification = $user_email_verification_service;
    $this->currentUser = $current_user;
    $this->request = $request_stack->getCurrentRequest();
    $this->configUserSettings = $config_factory->get('user.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user_email_verification.service'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'user_email_verification_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Prevent form usage with users who already verified email.
    if ($this->currentUser->isAuthenticated() && !$this->userEmailVerification->isVerificationNeeded()) {
      return [
        'notification' => [
          '#markup' => $this->t('Your Email %email was already verified.', ['%email' => $this->currentUser->getEmail()]),
          '#prefix' => '<p>',
          '#suffix' => '</p>',
        ],
      ];
    }

    if ($this->currentUser->isAnonymous()) {
      $form['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Username or Email address'),
        '#size' => 40,
        '#maxlength' => max(UserInterface::USERNAME_MAX_LENGTH, Email::EMAIL_MAX_LENGTH),
        '#required' => TRUE,
        '#default_value' => $this->request->query->get('name', ''),
      ];
    }
    else {
      $form['name'] = [
        '#type' => 'value',
        '#value' => $this->currentUser->getEmail(),
      ];
      $form['mail'] = [
        '#markup' => $this->t('Verify email message will be send to %email.', ['%email' => $this->currentUser->getEmail()]),
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];
    }

    $form['uid'] = [
      '#type' => 'value',
      '#value' => 0,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send verify mail'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $name_or_email = trim($form_state->getValue('name', ''));

    if ($name_or_email) {
      $active_users_only = $this->configUserSettings->get('register') != UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL;
      $user = $this->userEmailVerification->getUserByNameOrEmail($name_or_email, $active_users_only);

      if ($user instanceof UserInterface) {
        $form_state->setValue('uid', $user->id());
      }
      else {
        $form_state->setErrorByName('name', $this->t('Sorry, %name is not recognized as a user name or an Email address.', ['%name' => $name_or_email]));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $uid = trim($form_state->getValue('uid', 0));

    if ($uid) {
      $this->userEmailVerification->sendVerifyMailById($uid);
      $this->messenger()->addStatus($this->t('Further instructions have been sent to the Email address of a requested user.'));

      if ($this->currentUser->isAuthenticated()) {
        $form_state->setRedirect('entity.user.canonical', ['user' => $uid]);
      }
      else {
        $form_state->setRedirect('<front>');
      }
    }

  }

}
