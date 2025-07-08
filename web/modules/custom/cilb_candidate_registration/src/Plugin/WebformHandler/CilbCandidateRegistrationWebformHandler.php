<?php

namespace Drupal\cilb_candidate_registration\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    switch ($webform_submission->getCurrentPage()) {
      case 'registrant_personal_info':
        $this->registeringOnBehalfMessage($form_state);
        break;

      case 'authorize_credit_card_charge':
      case 'contribution_pagebreak':
        // we do this first before loading the billing address field page,
        // so the user can choose to use the preloaded or edit them
        // then we do it *again* after to ensure country and state are populated
        // because these seem to be corrupted by the chain selector otherwise
        $this->populateBillingAddress($form, $form_state);
        break;
    }
  }

  /**
   * If user is registering on behalf of another candidate, we remind
   * them to use the candidate details on this page
   */
  private function registeringOnBehalfMessage(FormStateInterface $formState) {
    if ($formState->getValue('candidate_representative') == 1) {
      \Drupal::messenger()->addWarning($this->t('Please ensure you enter the identifying information for <b>the candidate</b> on this page. You will need to be able to access their email to perform account verification.'));
    }
  }

  /**
   * Populate billing address fields
   *
   * For new contacts, these come from the fields on earlier pages
   * For existing contacts, those pages are skipped, so we need to fetch
   * the data from the database
   *
   * Note: we have to set the default_value keys in the form array for
   * it to be passed to the renderer
   */
  private function populateBillingAddress(array &$form, FormStateInterface $formState) {
    $targetPrefix = 'civicrm_1_contribution_1_contribution_billing_address';
    // instead we have to set defaults in the form array
    $formId = $formState->getFormObject()->getFormId();
    if ($formId == "webform_submission_register_english_add_form") {
      $targetFieldset = &$form["elements"]["authorize_credit_card_charge"]["civicrm_1_billing_1_number_of_billing_1_fieldset_fieldset"];
    }
    if ($formId == "webform_submission_backoffice_registration_add_form") {
      $targetFieldset = &$form["elements"]["contribution_pagebreak"]["civicrm_1_billing_1_number_of_billing_1_fieldset_fieldset"];
    }
    $sourceFields = [
      // fill name fields from contact
      'civicrm_1_contact_1_contact' => [
        'first_name',
        'last_name',
      ],
      'civicrm_1_contact_1_email' => [
        'email'
      ],
      // fill address fields from contact address
      'civicrm_1_contact_1_address' => [
        'street_address',
        'postal_code',
        'city',
        // 'country_id',
        'state_province_id',
      ],
    ];

    $values = $formState->getValues();

    $contactId = $values['civicrm_1_contact_1_contact_existing'];

    if (is_numeric($contactId)) {
      // for existing contacts, the form data won't contain some of the fields we
      // need, because these pages are skipped, so we need to fetch from the DB


      // check permissions is FALSE - we trust that CRM_Core_Session that
      // this is the current logged in contact, so they are allowed to
      // access their own data
      $apiData = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('id', '=', $contactId)
        ->addSelect(
          'first_name',
          'last_name',
          'address_primary.street_address',
          'address_primary.postal_code',
          'address_primary.city',
          'address_primary.country_id',
          'address_primary.state_province_id',
          // this isn't a field in the billing address section, but is required
          // by the credit card payment processor, so we need to load it
          // into the form state
          'email_primary.email'
        )
        ->execute()->single();

      // merge loaded data in to the formstate values only where blank
      // (in case for some reason those field have *not* been skipped)
      foreach ($sourceFields as $sourcePrefix => $fields) {
        // address fields are prefixed in the api result
        if ($sourcePrefix === 'civicrm_1_contact_1_address') {
          $apiPrefix = 'address_primary.';
        } elseif ($sourcePrefix === 'civicrm_1_contact_1_email') {
          $apiPrefix = 'email_primary.';
        } else {
          $apiPrefix = '';
        }
        foreach ($fields as $field) {
          $sourceKey = $sourcePrefix . '_' . $field;
          if (!$values[$sourceKey]) {
            $values[$sourceKey] = $apiData[$apiPrefix . $field];
          }
        }
      }

      // also add the email field - this isn't shown to the user but is required for credit card checkout
      $contactInfoFieldset = &$form['elements']['registrant_contact_info']['candidate_contact_information'];
      $contactInfoFieldset['phones']['civicrm_1_contact_1_email_email']['#default_value'] = $values['civicrm_1_contact_2_email_email'];
    }
    foreach ($sourceFields as $sourcePrefix => $fields) {
      $targetPrefix = ($sourcePrefix == 'civicrm_1_contact_1_email') ? 'civicrm_1_contact_2_email' : 'civicrm_1_contribution_1_contribution_billing_address';
      foreach ($fields as $field) {
        $targetKey = $targetPrefix . '_' . $field;
        if (!$values[$targetKey]) {
          $sourceKey = $sourcePrefix . '_' . $field;
          echo "Target Key: $targetKey, value: ";
          print_r($values[$sourceKey]);
          $formState->setValue($targetKey, $values[$sourceKey]);

          // unfortunately values in the form state are *not* passed to the renderer,
          // so the user cant see what's happening unless we set the default
          // in the form array as well
          $targetFieldset[$targetKey]['#default_value'] = $values[$sourceKey];
        }
      }
    }
  }


  /**
   * Submission hook to:
   * - handle creation of new Drupal user
   * - register contact for selected events
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->civicrm->initialize();

    $this->registerDrupalUser($webform_submission, $update);

    $this->registerEventParticipants($webform_submission, $update);
  }

  /**
   * Registers a Drupal user if no other user with the submitted email exists
   */
  protected function registerDrupalUser(WebformSubmissionInterface $webform_submission, $update = TRUE): void {
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
      // Only log an error if the user is not authenticated
      if (!\Drupal::currentUser()->isAuthenticated()) {
        // User with this email already exists, log a message and stop further processing
        \Drupal::logger('candidate_reg')->info('User with email ' . $email . ' already exists with UID: ' . $existing_user->id());
        \Drupal::messenger()->addError($this->t('A user with this email address already exists.'));
      }
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


    if (isset($webform_submission_data['civicrm_1_contact_1_contact_existing']) && is_numeric($webform_submission_data['civicrm_1_contact_1_contact_existing'])) {
      $results = \Civi\Api4\UFMatch::create(FALSE)
        ->addValue('domain_id', 1)
        ->addValue('uf_id', $user->id())
        ->addValue('contact_id', $webform_submission_data['civicrm_1_contact_1_contact_existing'])
        ->addValue('uf_name', $email)
        ->execute();
    }
  }

  /**
   * Register the CiviCRM contact for the selected events
   */
  protected function registerEventParticipants(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $webform_submission_data = $webform_submission->getData();
    $contactId = $webform_submission_data['civicrm_1_contact_1_contact_existing'];
    if (!is_numeric($contactId)) {
      return FALSE;
    }

    $eventIds = $webform_submission_data['event_ids'];

    // fetch event details for line items
    // (and to validate the events exist)
    $events = \Civi\Api4\Event::get(FALSE)
      ->addWhere('id', 'IN', $eventIds)
      ->addSelect('id', 'title')
      ->execute()
      ->indexBy('id')
      ->column('title');

    // remove any ids that weren't found
    $eventIds = array_keys($events);

    // when registering events, we add event payments to the webform contribution
    // need to know the webform contribution ID
    $webformCivicrmPostProcess = \Drupal::service('webform_civicrm.postprocess');
    $contributionId = $webformCivicrmPostProcess->getContributionId();
    $contribution = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $contributionId)
      ->addSelect('payment_instrument_id:name')
      ->execute()->first();
    if ($contribution['payment_instrument_id:name'] === 'Check') {
      $transactionId = $this->generateTransactionId();
      \Civi\Api4\Contribution::update(FALSE)
        ->addWhere('id', '=', $contributionId)
        ->addValue('check_number', $webform_submission_data['check_number'])
        ->addValue('contribution_status_id:name', 'Completed')
        ->addValue('trxn_id', $transactionId)
        ->execute();
    }

    // webform_civicrm will have created a single line item for the contribution
    // with the Total Amount Payable. we will need to update this
    //
    // NOTE: throw an error if more than one line item. That shouldn't happen,
    // and if it does the following logic might be totally wrong
    $defaultLineItem = (array) \Civi\Api4\LineItem::get(FALSE)
      ->addSelect('id', 'line_total')
      ->addWhere('contribution_id', '=', $contributionId)
      ->execute()
      ->single();

    $paidAmount = $defaultLineItem['line_total'];

    $seatFees = $this->getEventSeatFees($eventIds);
    $eventFeesPaid = array_sum(array_map(fn($fee) => $fee['amount_payable_now'], $seatFees));

    $formFeeAmount = $paidAmount - $eventFeesPaid;

    $formFeePriceFieldValueId = \Civi\Api4\PriceFieldValue::get(FALSE)
      ->addSelect('id')
      ->addWhere('price_field_id.price_set_id.name', '=', 'Registration_Form_Fee')
      ->execute()
      ->first()['id'] ?? 1;

    \CRM_Core_DAO::executeQuery(<<<SQL
      UPDATE `civicrm_line_item`
      SET
        `unit_price` = {$formFeeAmount},
        `line_total` = {$formFeeAmount},
        `label` = 'Registration Form Fee',
        `price_field_value_id` = {$formFeePriceFieldValueId}
      WHERE `id` = {$defaultLineItem['id']}
    SQL);

    foreach ($events as $eventId => $eventTitle) {
      try {
        $priceOption = $seatFees[$eventId];

        $participantId = \Civi\Api4\Participant::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('event_id', $eventId)
          ->addValue('register_date', 'now')
          ->addValue('Participant_Webform.Candidate_Representative_Name', $webform_submission_data['candidate_representative_name'])
          ->addValue('participant_fee_amount', $priceOption['amount'])
          ->addValue('participant_fee_level', $priceOption['label'])
          ->execute()
          ->first()['id'];

        // create additional line items in the contribution for the event registration fees

        // TODO: figure out what to do in the case that the client wants to record more than the form registration fee
        $params = [
          'entity_id' => $participantId,
          'entity_table' => 'civicrm_participant',
          'contribution_id' => $contributionId,
          'price_field_id' => $priceOption['price_field_id'],
          'price_field_value_id' => $priceOption['id'],
          'label' => "{$eventTitle} - CILB Candidate Registration - {$priceOption['label']}",
          'qty' => 1,
          'unit_price' => $priceOption['amount'],
          'line_total' => $priceOption['amount'],
          'participant_count' => 1,
          'financial_type_id' => $priceOption['financial_type_id'],
        ];
        // TODO: why are we calling BAO directly?
        \CRM_Price_BAO_LineItem::create($params);
      } catch (\Exception $e) {
        \Drupal::logger('candidate_reg')->debug('Unable to register contact ID ' . $contactId . ' for event ID ' . $eventId . ' because ' . $e->getMessage());
        \Drupal::messenger()->addError($this->t('Sorry, we were unable to register you for this exam. Please contact the administrator at %adminEmail', [
          '%adminEmail' => \Drupal::config('system.site')->get('mail'),
        ]));
      }
    }

    // update the contribution record to account for the
    // new line item total
    $lineItemAmounts = (array) \Civi\Api4\LineItem::get(FALSE)
      ->addSelect('line_total')
      ->addWhere('contribution_id', '=', $contributionId)
      ->execute()
      ->column('line_total');

    $newContributionTotal = array_sum($lineItemAmounts);

    // trying to update through the api triggers new payment
    // records - we just want the contribution total updated
    // TODO: check what this does financial transaction data
    \CRM_Core_DAO::executeQuery(<<<SQL
      UPDATE `civicrm_contribution` SET `total_amount` = {$newContributionTotal} WHERE `id` = $contributionId
    SQL);

    // now the registrations have been made, we're ready to send the receipt
    // we use the "invoice" task as its closest to our needs
    $params = [
      'output' => 'email_invoice',
      'from_email_address' => '"CILB" <info@jmaconsulting.biz>',
      'subject' => "CILB Candidate Registration Confirmation",
    ];
    \CRM_Contribute_Form_Task_Invoice::printPDF([$contributionId], $params, [$contactId]);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $current_page = $webform_submission->getCurrentPage();

    // Self-serve form
    if ($current_page == 'registrant_personal_info') {
      $this->validateAgeReq($form_state);
      $this->validateSSNMatch($form_state);
      $this->validateUniqueUser($form_state);
    } elseif ($current_page == 'exam_fee_page') {
      $this->validateContributionAmount($form_state);
    } elseif ($current_page == 'user_identification') {
      $this->validateCandidateRep($form_state);
    } elseif ($current_page == 'payment_options') {
      $this->redirectPayByCheck($form_state);
    } elseif ($current_page == 'select_exam_page') {
      $this->validateParticipantStatus($form_state);
      $this->validateExamPreference($form_state);
    }

    // Backoffice registration
    if ($current_page == 'candidate_information') {
      $this->validateAgeReq($form_state);
      $this->validateSSNMatch($form_state);
      $this->validateUniqueUser($form_state);
    } elseif ($current_page == 'exam_information') {
      $this->validateParticipantStatus($form_state);
      $this->validateExamPreference($form_state);
    } elseif ($current_page == 'payment_information') {
      $this->validateContributionAmount($form_state);
    }
  }

  private function validateExamPreference(FormStateInterface $formState) {
    $examPreferences = $formState->getValue('exam_preference');
    if (isset($examPreferences) && count($examPreferences) > 1) {
      $events = \Civi\Api4\Event::get(FALSE)
        ->addSelect('Exam_Details.Exam_Part', 'Exam_Details.Exam_Part:label')
        ->addWhere('id', 'IN', $examPreferences)
        ->execute();
      foreach ($events as $event) {
        if (empty($selectedEvents[$event['Exam_Details.Exam_Part']])) {
          $selectedEvents[$event['Exam_Details.Exam_Part']] = [$event['id']];
        } else {
          $error_message = $this->t('You cannot select more then one ' . $event['Exam_Details.Exam_Part:label'] . ' event');
          $formState->setErrorByName('exam_preference', $error_message);
          break;
        }
      }
    }
  }

  /**
   * If user chooses pay by check, immediately redirect to the pay by mail page
   */
  private function redirectPayByCheck($formState) {
    if ($formState->getValue('please_select_mode_of_payment') == 2) {
      $redirect = new RedirectResponse('register-by-mail');
      $redirect->send();
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

    if ($ssn !== $ssnMatch && !$formState->isValueEmpty('verify_ssn')) {
      $error_message = $this->t('The SSNs do not match. Please check the numbers and try again.');
      $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_5', $error_message);
      $formState->setErrorByName('verify_ssn', $error_message);
    }
  }

  /**
   * Validate no user shares the same DOB and SSN.
   */
  private function validateUniqueUser(FormStateInterface $formState, WebformSubmissionInterface $webform_submission) {
    // If not logged in, check SSN against existing contacts
    $contactId = $webform_submission->getData()['civicrm_1_contact_1_contact_existing'];
    if (!is_numeric($contactId)) {
      // No existing contact found
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

        if (!$user || $user->isBlocked()) {
          // user account exists but is blocked
          $error_message = $this->t('A user account already exists with this Social Security Number. Please contact %adminEmail for assistance.', [
            '%adminEmail' => \Drupal::config('system.site')->get('mail'),
          ]);
          $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_5', $error_message);
          // given we are directing the user to the admin, leave a log message to help the admin diagnose
          $logMessage = "New user registration error: a site visitor tried to register with SSN {$ssn}, but this matched existing CiviCRM Contact {$matchingContact['id']}, which is linked to Drupal User ID {$matchingUser['uf_id']}, but the Drupal User is missing or blocked. The visitor was directed to contact the site admin.";
          \Drupal::logger('candidate_reg')->notice($logMessage);
          return;
        }

        // A matching SSN was found for a CiviCRM contact as well as a different Drupal user
        // => direct to login
        $error_message = $this->t('A user account already exists with this Social Security Number. Please <a href="/user/login">login</a> first in order to continue registration, or contact %adminEmail for assistance.', [
          '%adminEmail' => \Drupal::config('system.site')->get('mail'),
        ]);
        $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_5', $error_message);

        // additional notification for proxy registrations
        if ($formState->getValue('candidate_representative') == 1) {
          \Drupal::messenger()->addWarning($this->t('Please note you will require access to the candidate\'s user account credentials to continue.'));
        }
        return;
      }

      // there is a legacy contact record, without a Drupal account
      // => direct to "activate" their account (create
      //    new Drupal user for their contact)
      $error_message = $this->t('A candidate record for this Social Security Number already exists. Please <a href="/user/activate">re-activate your account</a> before continuing.');
      $formState->setErrorByName('civicrm_1_contact_1_cg1_custom_5', $error_message);

      // additional notification for proxy registrations
      if ($formState->getValue('candidate_representative') == 1) {
        \Drupal::messenger()->addWarning($this->t('Please note you will require access to the candidate\'s email to complete the activation process.'));
      }
      return;
    }
  }

  /**
   * Validate the total contribution amount
   *
   * This checks the client side calculation matches the server side one. But it might be
   * better to just use the server side calc
   */
  private function validateContributionAmount(FormStateInterface $formState) {
    $examFee = (int) $formState->getValue('civicrm_1_contribution_1_contribution_total_amount');

    if (!$examFee) {
      $formState->setErrorByName('exam_fee_markup', $this->t('Payable amount is missing'));
      return;
    }

    $eventIds = $formState->getValue('event_ids');

    if ($examFee !== $this->getPayableNowAmount($eventIds)) {
      $formState->setErrorByName('exam_fee_markup', $this->t('Payable amount is incorrect'));
      return;
    }
  }

  /*
  * Validate the Candidate Rep name field
  */
  private function validateCandidateRep(FormStateInterface $formState) {
    $isRep = $formState->getValue('candidate_representative');
    $repName = $formState->getValue('candidate_representative_name');

    if ($isRep == 1 && $repName == '') {
      $formState->setErrorByName('candidate_representative_name', $this->t('Enter your full name to continue.'));
      return;
    }
  }

  /*
  * Validation to make sure Candidate is not already registered for exam
  */
  private function validateParticipantStatus(FormStateInterface $formState) {
    $eventIds = $formState->getValue('civicrm_1_participant_1_participant_event_id');
    $contactID = $formState->getValue('civicrm_1_contact_1_contact_existing');

    // If the user is logged in
    if ($contactID) {
      $participants = \Civi\Api4\Participant::get(FALSE)
        ->addWhere('contact_id', '=', $contactID)
        ->addWhere('event_id', 'IN', $eventIds)
        ->addWhere('status_id:label', '!=', 'Cancelled')
        ->addWhere('status_id:label', '!=', 'Expired')
        ->execute();

      if (count($participants)) {
        $formId = $formState->getFormObject()->getFormId();

        if ($formId == "webform_submission_register_english_add_form") {
          $message = 'You are already registered for this exam.';
        } elseif ($formId == "webform_submission_backoffice_registration_add_form") {
          $message = 'The candidate is already registered for this exam.';
        } else { // Default in case we want to use this handler with another form
          $message = 'The candidate is already registered for this exam.';
        }
        $formState->setErrorByName('civicrm_1_participant_1_participant_event_id', $this->t($message));
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
        ->addWhere('event_id', 'IN', $eventIds)
        ->execute();

      if (count($participants)) {
        $formState->setErrorByName('civicrm_1_participant_1_participant_event_id', $this->t('Another user with the same email and name has already registered for this exam.'));
      }
    }
  }

  /**
   * For a set of event IDs, get their price option data
   *
   * NOTE: this assumes that each event has 1 configured price set
   * with 1 price field with 1 price option.
   *
   * If there are multiple this will make an arbitrary choice (by lowest ID?)
   *
   */
  private function getEventSeatFees(array $eventIds): array {
    // fetch price sets
    $priceSetsByEventId = \Civi\Api4\PriceSetEntity::get(FALSE)
      ->addSelect('price_set_id', 'entity_id')
      ->addWhere('entity_table', '=', 'civicrm_event')
      ->addWhere('entity_id', 'IN', $eventIds)
      ->execute()
      ->indexBy('entity_id')
      ->column('price_set_id');

    $priceOptionsByPriceSetId = \Civi\Api4\PriceFieldValue::get(FALSE)
      ->addWhere('price_field_id.price_set_id', 'IN', $priceSetsByEventId)
      ->addSelect(
        // price field value fields
        'id',
        'amount',
        'financial_type_id',
        'label',
        // price field fields
        'price_field_id',
        'price_field_id.price_set_id'
      )
      ->execute()
      ->indexBy('price_field_id.price_set_id');

    $priceOptionsByEventId = [];

    foreach ($eventIds as $eventId) {
      $priceSetId = $priceSetsByEventId[$eventId];
      $priceOption = $priceOptionsByPriceSetId[$priceSetId];
      $priceOptionsByEventId[$eventId] = $priceOption;
    }

    // TODO: this seems slightly arbitrary business logic
    $eventsPayableNow = (array) \Civi\Api4\Event::get(FALSE)
      ->addSelect('id')
      ->addWhere('id', 'IN', $eventIds)
      ->addWhere('Exam_Details.Exam_Format', '=', 'paper')
      ->execute()
      ->column('id');

    foreach ($priceOptionsByEventId as $eventId => $priceOption) {
      $payableNow = in_array($eventId, $eventsPayableNow);
      $priceOptionsByEventId[$eventId]['is_payable_now'] = $payableNow;
      $priceOptionsByEventId[$eventId]['amount_payable_now'] = $payableNow ? $priceOption['amount'] : 0;
    }

    return $priceOptionsByEventId;
  }


  /**
   * Get the total amount payable now
   *
   * NOTE: this is currently calculated client side on the webform.
   *
   * we have a validation step to ensure the submitted amount matches, but it would probably
   * be better to pass the server side calc to the form explicitly
   *
   * (we would need to pass the line item details for display also. i suppose we could form alter a #markup element)
   */
  private function getPayableNowAmount($eventIds): int {
    $eventFees = $this->getEventSeatFees($eventIds);
    $eventAmountsPayableNow = array_map(fn($fee) => $fee['amount_payable_now'], $eventFees);
    $eventTotal = array_sum($eventAmountsPayableNow);

    $formFee = \Civi\Api4\PriceFieldValue::get(FALSE)
      ->addSelect('amount')
      ->addWhere('price_field_id.price_set_id.name', '=', 'Registration_Form_Fee')
      ->execute()
      ->first()['amount'] ?? 135;

    return $eventTotal + $formFee;
  }

  /**
   * Generates a pseduo-random transaction id starting with 99. It is guaranteed to be unique in the database
   */
  private function generateTransactionId(): string {
    do {
      $id = '99' . bin2hex(random_bytes(5));
      $num_contributions = \Civi\Api4\Contribution::get(FALSE)
        ->addWhere('id', '=', $id)
        ->execute()->count();
    } while ($num_contributions > 0);
    return $id;
  }
}
