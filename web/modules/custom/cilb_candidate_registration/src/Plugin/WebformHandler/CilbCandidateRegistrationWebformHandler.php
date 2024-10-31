<?php

namespace Drupal\cilb_candidate_registration\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\Entity\User;

/**
 * Sace CiviCRM Activity Update Handler.
 *
 * @WebformHandler(
 *   id = "cilb_candidate_registration_handler",
 *   label = @Translation("Cilb Candidate Registration Handler"),
 *   category = @Translation("CRM"),
 *   description = @Translation("Webform Handler for CILB Candidate Registration Form"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class CilbCandidateRegistrationWebformHandler extends WebformHandlerBase {

  /**
   * The CiviCRM service.
   *
   * @var \Drupal\civicrm\Civicrm
   */
  protected $civicrm;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->civicrm = $container->get('civicrm');
    $instance->database = \Drupal::database();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $current_page = $webform_submission->getCurrentPage();

    if ($current_page == 'registrant_personal_info') {
      $this->validateAgeReq($form_state);
      $this->validateSSNMatch($form_state);
      $this->validateUniqueUser($form_state);
    }
    elseif ($current_page == 'exam_fee_page') {
      $this->validateExamFee($form_state);
    }
    elseif ($current_page == 'user_identification') {
      $this->validateCandidateRep($form_state);
    }
    elseif ($current_page == 'select_exam_page') {
      //TODO how to register for multiple events at once?
      $this->validateParticipantStatus($form_state);
    }
  }

  /**
  * Submission hook to:
  * - handle creation of new Drupal user
  * - register contact for selected events
  */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->civicrm->initialize();

    if (!\Drupal::currentUser()->isAuthenticated()) {
      $userCreate = $this->registerDrupalUser($webform_submission, $update);
    }
    $this->registerEventParticipants($webform_submission, $update);
  }

  protected function registerDrupalUser(WebformSubmissionInterface $webform_submission, $update = TRUE): bool {
    $webform_submission_data = $webform_submission->getData();

    $email = $webform_submission_data['civicrm_1_contact_1_email_email'];
    $firstName = $webform_submission_data['civicrm_1_contact_1_contact_first_name'];
    $lastName = $webform_submission_data['civicrm_1_contact_1_contact_last_name'];
    $baseUsername = $firstName . $lastName;
    $username = $baseUsername;

    // Check if a user with the given email already exists
    //
    // NOTE: this is unlikely, as we check anonymous users
    // are using an unrecognised SSN. But it's possible a
    // different person is trying to re-use a shared email
    // ( johnandliz@thesmiths.com ? )
    //
    // In this case we throw an error to prevent any further
    // processing
    $existing_user = user_load_by_mail($email);
    if ($existing_user) {
        // User with this email already exists, log a message and stop further processing
        \Drupal::logger('candidate_reg')->info('User with email ' . $email . ' already exists with UID: ' . $existing_user->id());
        \Drupal::messenger()->addError($this->t('A user with this email address already exists.'));
        return;
    }

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


    if(isset($webform_submission_data['civicrm_1_contact_1_contact_existing'])) {
      $results = \Civi\Api4\UFMatch::create(FALSE)
        ->addValue('domain_id', 1)
        ->addValue('uf_id', $user->id())
        ->addValue('contact_id', $webform_submission_data['civicrm_1_contact_1_contact_existing'])
        ->addValue('uf_name', $email)
        ->execute();
    }

    return TRUE;
  }

  protected function registerEventParticipants(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $webform_submission_data = $webform_submission->getData();
    $contactId = $webform_submission_data['civicrm_1_contact_1_contact_existing'] ?? NULL;

    if (!$contactId) {
      return FALSE;
    }

    $eventIds = $webform_submission_data['event_ids'];

    foreach ($eventIds as $eventId) {
      try {
        \Civi\Api4\Participant::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('event_id', $eventId)
          ->addValue('register_date', 'now')
          ->execute();
      }
      catch (\Exception $e) {
        \Drupal::logger('candidate_reg')->debug('Unable to register contact ID ' . $contactID . ' for event ID ' . $eventId . ' because ' . $e->getMessage());
        \Drupal::messenger()->addError($this->t('Sorry, we were unable to register you for event ID ' . $eventId));
      }
    }
  }

  /**
  * Validate candidate birthdate to ensure they are 16 years or older.
  */
  private function validateAgeReq(FormStateInterface $formState) {
    $birthday = $formState->getValue('civicrm_1_contact_1_contact_birth_date');
    $birthDate = \DateTime::createFromFormat('Y-m-d', $birthday);

    if ($birthDate === false) {
        $formState->setErrorByName('civicrm_1_contact_1_contact_birth_date', $this->t('Please enter a valid birth date.'));
        return;
    }

    $today = new \DateTime();

    // Get years between today and birthday
    $age = $today->diff($birthDate)->y;

    // If candidate is not 16 years or older
    if ($age < 16) {
        $formState->setErrorByName('civicrm_1_contact_1_contact_birth_date', $this->t('In order to create an account with PTI Online Services and register for the DBPR/BET exam, you must be at least 16 years old.'));
    }
  }

  /**
  * Validate SSN entries.
  */
  private function validateSSNMatch(FormStateInterface $formState) {
    $ssn = $formState->getValue('civicrm_1_contact_1_cg1_custom_5');
    $ssnMatch = $formState->getValue('verify_ssn');

    if($ssn !== $ssnMatch && !\Drupal::currentUser()->isAuthenticated()) {
      $error_message = $this->t('The SSNs do not match. Please check the numbers and try again.');
      $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_5', $error_message);
      $formState->setErrorByName('verify_ssn', $error_message);
    }
  }

  /**
  * Validate no user shares the same DOB and SSN.
  */
  private function validateUniqueUser(FormStateInterface $formState) {
    // If not logged in, check SSN against existing contacts
    if (!\Drupal::currentUser()->isAuthenticated()) {
      $ssn = $formState->getValue('civicrm_1_contact_1_cg1_custom_5');

      $this->civicrm->initialize();

      // note we use ->first - presuming that SSNs
      // should be unique for untrashed contacts
      $matchingContact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('id')
        ->addWhere('Registrant_Info.SSN', '=', $ssn)
        ->addWhere('is_deleted', '=', FALSE)
        ->execute()
        ->first();

      if (!$matchingContact) {
        // no existing record matching this SSN.
        // => the user can continue as anonymous
        // and a new contact/user will be created in
        // postSave
        return;
      }

      // if we have matching contacts, we need to check
      // if they have a user record or not

      $matchingUser = \Civi\Api4\UFMatch::get(FALSE)
        ->addWhere('contact_id', '=', $matchingContact['id'])
        ->execute()
        ->first();

      if ($matchingUser) {
        // user account already exists for this SSN
        // => check if it's legit or blocked user
        $user = User::load($matchingUser['uf_id']);

        if ($user->isBlocked()) {
          // user account exists but is blocked
          $error_message = $this->t('A blocked user account already exists with this Social Security Number. Please contact us for assistance.');
          return;
        }

        // => direct to login
        $error_message = $this->t('A user account already exists with this Social Security Number. <a href="/user/login">Please login first in order to continue registration</a>, or contact for assistance.');
        $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_5', $error_message);
        return;
      }

      // there is a legacy contact record, without a Drupal account
      // => direct to "activate" their account (create
      //    new Drupal user for their contact)
      $error_message = $this->t('An exam record for this Social Security Number already exists, but you must <a href="/user/re-activate">re-activate your account before continuing</a>.');
      $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_5', $error_message);
      return;
    }
  }

  /**
  * Validate exam fee selection
  */
  private function validateExamFee(FormStateInterface $formState) {
    $examFee = $formState->getValue('civicrm_1_contribution_1_contribution_total_amount');

    if ($examFee == 0) {
        $formState->setErrorByName('exam_fee_markup', $this->t('Exam fee is missing'));
        return;
    }
  }

  /*
  * Validate the Candidate Rep name field
  */
  private function validateCandidateRep(FormStateInterface $formState) {
    $isRep = $formState->getValue('candidate_representative');
    $repName = $formState->getValue('civicrm_1_contact_1_cg1_custom_7');

    if ($isRep == 1 && $repName == '') {
        $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_7', $this->t('Enter your full name to continue.'));
        return;
    }
  }

  /*
  * Validation to make sure Candidate is not already registered for exam
  */
  private function validateParticipantStatus(FormStateInterface $formState) {
    $eventIDs = $formState->getValue('civicrm_1_participant_1_participant_event_id');
    $contactID = $formState->getValue('civicrm_1_contact_1_contact_existing');

    // If the user is logged in
    if($contactID) {
      $participants = \Civi\Api4\Participant::get(FALSE)
        ->addWhere('contact_id', '=', $contactID)
        ->addWhere('event_id', 'IN', $eventIDs)
        ->addWhere('status_id:label', '!=', 'Cancelled')
        ->addWhere('status_id:label', '!=', 'Expired')
        ->execute();

      if(count($participants)) {
        $formState->setErrorByName('civicrm_1_participant_1_participant_event_id', $this->t('You are already registered for this exam.'));
      }

    }

    // If no contact is created for the registrant yet, check for duplicates by name and email
    else {
      $firstName = $formState->getValue('civicrm_1_contact_1_contact_first_name');
      $lastName = $formState->getValue('civicrm_1_contact_1_contact_last_name');
      $email = $formState->getValue('civicrm_1_contact_1_email_email');

      $participants = \Civi\Api4\Participant::get(FALSE)
        ->addJoin('Email AS email', 'LEFT', ['email.id', '=', 'contact_id.email_primary'])
        ->addWhere('contact_id.first_name', '=', $firstName)
        ->addWhere('contact_id.last_name', '=', $lastName)
        ->addWhere('email.email', '=', $email)
        ->addWhere('event_id', 'IN', $eventIDs)
        ->execute();

      if(count($participants)) {
        $formState->setErrorByName('civicrm_1_participant_1_participant_event_id', $this->t('Another user with the same email and name has already registered for this exam.'));
      }
    }
  }
}
