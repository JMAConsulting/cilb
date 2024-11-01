<?php

namespace Drupal\cilb_candidate_registration\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\civicrm\Civicrm;

/**
 * Form for "activating" a legacy user = creating a Drupal
 * user for an existing contact in CiviCRM, where no user
 * currently exists
 */
class UserActivate extends FormBase implements ContainerInjectionInterface {

  /**
   * The CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * Class constructor.
   */
  public function __construct(Civicrm $civicrm) {
    $civicrm->initialize();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('civicrm')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cilb_user_activate';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['intro'] = [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('Enter your Social Security Number and email address below to activate your user acccount.') . '</p>',
    ];

    $form['ssn'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Social Security Number'),
      '#mask' => [
        'value' => '999-99-9999',
      ],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (\Drupal::currentUser()->isAuthenticated()) {
      throw new \Drupal\webform\WebformException($this->t('You are already logged in. To activate another user account, you must log out first.'));
    }

    // check for SSN match
    $ssn = $form_state->getValue('ssn');

    // note we use ->first - presuming that SSNs
    // should be unique for untrashed contacts
    $matchingContact = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'email_primary.email')
      ->addWhere('Registrant_Info.SSN', '=', $ssn)
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()
      ->first();

    // if SSN doesn't match, say so
    if (!$matchingContact) {
      $message = $this->t('The SSN you have entered doesn\'t match any existing contact records on our system. You can register for an examination directly, and a new user account will be created for you.');
      $form_state->setErrorByName('ssn', $message);
      return;
    }

    // if SSN matches but user already exists => direct to login
    $existingUser = \Civi\Api4\UFMatch::get(FALSE)
      ->addWhere('contact_id', '=', $matchingContact['id'])
      ->execute()
      ->first();

    if ($existingUser) {
      $message = $this->t('An account has already been activated for the SSN you have entered. <br /> Please <a href="/user/login">proceed to login</a> or <a href="/user/password">reset your password if you have forgotten it</a>.');
      $form_state->setErrorByName('ssn', $message);
      return;
    }

    // check email match
    $submittedEmail = trim($form_state->getValue('email'));
    $recordEmail = $matchingContact['email_primary.email'];

    // if SSN matches but email is wrong, show obfuscated email
    if ($submittedEmail !== $recordEmail) {
      $emailParts = explode('@', $recordEmail);
      $obfuscParts = array_map(fn ($part) => substr($part, 0, 3) . '*******', $emailParts);
      $obfuscEmail = implode('@', $obfuscParts);

      $message = $this->t('Our records indicate a different email for the SSN you have entered (%email). Please try again or contact %adminEmail for assistance.', [
        '%email' => $obfuscEmail,
        '%adminEmail' => \Drupal::config('system.site')->get('mail'),
      ]);
      $form_state->setErrorByName('email', $message);
      return;
    }

    // checks pass => good to go :)
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // load existing matching contact
    $ssn = $form_state->getValue('ssn');
    $email = trim($form_state->getValue('email'));

    $contact = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id', 'first_name', 'last_name')
      ->addWhere('Registrant_Info.SSN', '=', $ssn)
      ->addWhere('email_primary.email', '=', $email)
      ->addWhere('is_deleted', '=', FALSE)
      ->execute()
      ->first();

    // contact should exist based on validate checks
    if (!$contact) {
      $this->logger('candidate_reg')->warning('Existing contact record could not be found in UserActivate::submitForm for email %email - validation has failed somehow?', [
        '%email' => $email
      ]);
      $this->messenger()->addError($this->t('Matching contact record could not be found. If you need assistance, please contact %adminEmail.', [
        '%adminEmail' => \Drupal::config('system.site')->get('mail'),
      ]));
      return;
    }

    $this->createCandidateUserAccount(
      $email,
      $contact['first_name'] . $contact['last_name'],
      $contact['id']
    );

    // on successful submit, redirect to login form
    $this->messenger()->addStatus($this->t('User activation was successful. You should receive an email with a link to set a password shortly.'));
    $form_state->setRedirectUrl(new Url('user.login'));
  }

  /**
   * Create a new candidate account
   *
   * TODO: this could be factored out to shared utility for the
   * webform handler as well
   *
   * @param string $email
   * @param string $baseUsername
   * @param int $contactId
   */
  protected function createCandidateUserAccount($email, $baseUsername, $contactId) {
    // Check if a user with the given email already exists
    //
    // NOTE: this is unlikely, as the validate checks this
    // contact does not already have a user attached.
    //
    // But it's possible two contacts are trying to re-use a shared email
    // ( johnandliz@thesmiths.com ? )
    //
    // In this case we throw an error to prevent any further processing
    $existingUser = user_load_by_mail($email);
    if ($existingUser) {
        // User with this email already exists, log a message and stop further processing
        $this->logger('candidate_reg')->info('User with email ' . $email . ' already exists with UID: ' . $existingUser->id());
        $this->messenger()->addError($this->t('A user with this email address already exists.'));
        return;
    }

    $username = $baseUsername;

    // Check if a user with the same username already exists but has a different email
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    $query = $user_storage->getQuery()
      ->condition('name', $username)
      ->condition('mail', $email, '<>')
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($query)) {
        // User with the same username but a different email exists, increment username
        $i = 1;
        while (user_load_by_name($username)) {
            $username = $baseUsername . $i;
            $i++;
        }
    }

    // Create a new Drupal user
    $user = User::create();

    // Set the user properties
    $user->setEmail($email);
    $user->setUsername($username);
    $user->addRole('candidate');

    $user->activate();
    $user->enforceIsNew();
    $user->save();

    // Send the email verification email
    \Drupal::service('plugin.manager.mail')->mail(
      'user',
      'register_no_approval_required',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      ['account' => $user],
      NULL,
      TRUE
    );

    \Civi\Api4\UFMatch::create(FALSE)
      ->addValue('domain_id', 1)
      ->addValue('uf_id', $user->id())
      ->addValue('contact_id', $contactId)
      ->addValue('uf_name', $email)
      ->execute();
  }

}
