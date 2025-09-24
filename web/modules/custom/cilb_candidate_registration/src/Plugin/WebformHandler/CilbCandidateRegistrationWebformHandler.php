<?php

namespace Drupal\cilb_candidate_registration\Plugin\WebformHandler;

use CRM_Core_Session;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\user\Entity\User;
use Drupal\webform\Utility\WebformFormHelper;
use Civi\Api4\OptionValue;

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
    $form['#attached']['library'][] = 'cilb_candidate_registration/candidate_registration';
    $form['#attached']['library'][] = 'cilb_candidate_registration/exam_selection';

    // save typing when testing
    if (\Drupal::request()->get('test_prefill')) {
      $this->testPrefill($form);
    }

    switch ($webform_submission->getCurrentPage()) {
      case 'registrant_personal_info':
        $this->registeringOnBehalfMessage($form_state);
        break;

      case 'select_category_page':
        $this->setCategoryOptions($form, $form_state);
        break;

      case 'select_exam_page':
        // note on frontend form:
        // - the event category is selected on an earlier page, so this will limit the options directly
        // - limit options based on logged in contact, or none
        $selectedCategory = (int) $form_state->getValue('select_category_id');
        $this->setEventOptions($form, $form_state, $selectedCategory);
        break;

      case 'exam_information':
        // note on backoffice form:
        // - provide all event options, filter on the fly (see examSelection.js)
        // - limit options based on selected contact, rather than logged in
        $this->setEventOptions($form, $form_state);
        $this->setCategoryOptions($form, $form_state);
        break;

      case 'authorize_credit_card_charge':
      case 'contribution_pagebreak':
        // we do this first before loading the billing address field page,
        // so the user can choose to use the preloaded or edit them
        // then we do it *again* after to ensure country and state are populated
        // because these seem to be corrupted by the chain selector otherwise
        $this->populateBillingAddress($form, $form_state);
        break;

      case 'definition_scope_of_practice':
        $this->renderScope($form, $form_state);
        break;

      case 'exam_fee_page':
        $this->setContributionAmount($form, $form_state);
        $this->renderFeeTable($form, $form_state);
        break;
    }
  }

  private function testPrefill(array &$form) {
    $elements = WebformFormHelper::flattenElements($form);

    $testPrefill = [
      // contact
      'civicrm_1_contact_1_contact_first_name' => 'test',
      'civicrm_1_contact_1_contact_last_name' => 'test',
      'civicrm_1_contact_1_contact_birth_date' => '1990-01-01',
      // TODO: custom field ids vary on deployments
      // ssn
      'civicrm_1_contact_1_cg1_custom_5' => '111-11-1222',
      'verify_ssn' => '111-11-1222',
      // lang / ada / bac
      'civicrm_1_contact_1_cg1_custom_3' => '1',
      'civicrm_1_contact_1_cg1_custom_2' => '0',
      'civicrm_1_contact_1_cg1_custom_4' => '0',
      'candidate_has_degree' => '0',
      // address
      'civicrm_1_contact_1_address_street_address' => 'test st',
      'civicrm_1_contact_1_address_city' => 'test town',
      'civicrm_1_contact_1_address_state_province_id' => 1000,
      'civicrm_1_contact_1_address_postal_code' => 12345,
      // emailphone
      'civicrm_1_contact_1_email_email' => 'test@test.example',
      'civicrm_1_contact_1_phone_phone' => 1234521342,
    ];

    foreach (\array_intersect_key($testPrefill, $elements) as $key => $value) {

      $elements[$key]['#default_value'] = $value;
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
          // echo "Target Key: $targetKey, value: ";
          // print_r($values[$sourceKey]);
          $formState->setValue($targetKey, $values[$sourceKey]);

          // unfortunately values in the form state are *not* passed to the renderer,
          // so the user cant see what's happening unless we set the default
          // in the form array as well
          $targetFieldset[$targetKey]['#default_value'] = $values[$sourceKey];
        }
      }
    }
  }

  private function renderScope(array &$form, FormStateInterface $form_state): void {
    $elements = WebformFormHelper::flattenElements($form);
    $catId = $form_state->getValue('select_category_id');

    $category = $catId ? \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect("label", "description")
      ->addWhere("value", "=", $catId)
      ->addWhere('option_group_id:name', '=', 'event_type')
      ->execute()
      ->first() : NULL;

    if (!$category) {
      $elements['scope_markup']['#markup'] = "[No category selected]";
      $elements['definition_scope_of_practice_1']['#title'] = $this->t('Definition & Scope of Practice');
      return;
    }

    // note this needs to work if we go back and change the category and/or the category is not found
    $elements['scope_markup']['#markup'] = $category['description'] ?: "[Exam category description missing]";
    $elements['definition_scope_of_practice_1']['#title'] = $this->t('Definition & Scope of Practice') . ($category['label'] ? " - {$category['label']}" : "");
  }

  /**
   * Set the total contribution amount based on event ids
   */
  private function setContributionAmount(array &$form, FormStateInterface $formState) {
    $eventIds = $formState->getValue('select_exam_ids');
    $amount = $this->getPayableNowAmount($eventIds);
    $elements = WebformFormHelper::flattenElements($form);
    $elements['civicrm_1_contribution_1_contribution_total_amount']['#default_value'] = $amount;
  }

  private function renderFeeTable(array &$form, FormStateInterface $form_state): void {
    $eventIds = $form_state->getValue('select_exam_ids');
    $elements = WebformFormHelper::flattenElements($form);

    $feeLines = [];

    $feeLines[] = [
      'payable_now' => TRUE,
      'description' => $this->t('Registration Fee'),
      'amount' => $this->getFormFeeAmount(),
    ];

    $feesPayableNow = $this->getEventFeesPayableNow($eventIds);
    $feesPayableLater = $this->getEventFeesPayableLater($eventIds);

    // fetch and flatten event lines
    // we combine by event id first so that payable/non payable for the same
    // event are next to each other
    $feeLinesByEvent = [];

    foreach ($feesPayableNow as $eventId => $fees) {
      $feeLinesByEvent[$eventId] ??= [];
      foreach ($fees as $fee) {
        $feeLinesByEvent[$eventId][] = [
          'payable_now' => TRUE,
          'description' => $fee['label'],
          'amount' => $fee['amount'],
        ];

      }
    }

    foreach ($feesPayableLater as $eventId => $fees) {
      $feeLinesByEvent[$eventId] ??= [];
      foreach ($fees as $fee) {
        $feeLinesByEvent[$eventId][] = [
          'payable_now' => FALSE,
          'description' => $fee['label'],
          'amount' => $fee['amount'],
        ];
      }
    }

    $feeLines = array_merge($feeLines, array_merge(...array_values($feeLinesByEvent)));
    foreach ($feeLines as &$line) {
      $line['payable_marker'] = $line['payable_now'] ? '✔' : '';
    }

    $totalAmount = array_sum(array_map(fn ($line) => $line['amount'], $feeLines));
    $totalPayableNow = array_sum(array_map(fn ($line) => $line['payable_now'] ? $line['amount'] : 0, $feeLines));

    $tableRows = array_map(fn ($line) => "
      <tr class='exam-fee'>
        <td class='candidate-fee-title'>{$line['description']}</td>
        <td class='candidate-fee-amount'>{$line['amount']}</td>
        <td class='candidate-fee-payable'>{$line['payable_marker']}</td>
      </tr>", $feeLines);

    $tableRows[] = "<tr class='total-fee'>
        <td class='candidate-fee-title'>Total fees</td>
        <td class='candidate-fee-amount'>{$totalAmount}</td>
      </tr>";

    if ($totalPayableNow !== $totalAmount) {
      $tableRows[] = "<tr class='total-payable-now'>
         <td class='candidate-fee-title'>Total payable now</td>
         <td class='candidate-fee-amount'>{$totalPayableNow}</td>
       </tr>";
    }

    $tableRows = implode("\n", $tableRows);

    $markup = <<<HTML
      <table class="candidate-fee-table" width="100%">

        <tr>
          <th class="candidate-fee-title">{$this->t('Item')}</th>
          <th class="candidate-fee-amount">{$this->t('Amount')}</th>
          <th class="candidate-fee-payable">{$this->t('Payable now?')}</th>
        </tr>

        {$tableRows}

      </table>
    HTML;

    $elements['exam_fee_markup']['#markup'] = $markup;
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

    $eventIds = $webform_submission_data['select_exam_ids'];

    // fetch event details for line items
    // (and to validate the events exist)
    $eventIds = \Civi\Api4\Event::get(FALSE)
      ->addWhere('id', 'IN', $eventIds)
      ->addSelect('id')
      ->execute()
      ->column('id');

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

    $eventFees = $this->getEventFeesPayableNow($eventIds);
    $eventFeesTotal = array_sum(array_map(fn ($fee) => $fee['amount'], $eventFees));

    $formFeeAmount = $paidAmount - $eventFeesTotal;

    $formFeePriceFieldValueId = \Civi\Api4\PriceFieldValue::get(FALSE)
      ->addSelect('id')
      ->addWhere('price_field_id.price_set_id.name', '=', 'Registration_Form_Fee')
      ->execute()
      ->first()['id'] ?? 1;

    // adjust the original line item to reflect the registration fee
    \CRM_Core_DAO::executeQuery(<<<SQL
      UPDATE `civicrm_line_item`
      SET
        `unit_price` = {$formFeeAmount},
        `line_total` = {$formFeeAmount},
        `label` = 'Registration Form Fee',
        `price_field_value_id` = {$formFeePriceFieldValueId}
      WHERE `id` = {$defaultLineItem['id']}
    SQL);

    foreach ($eventIds as $eventId) {
      try {
        $participantId = \Civi\Api4\Participant::create(FALSE)
          ->addValue('contact_id', $contactId)
          ->addValue('event_id', $eventId)
          ->addValue('register_date', 'now')
          ->addValue('Participant_Webform.Candidate_Representative_Name', $webform_submission_data['candidate_representative_name'] ?? NULL)
          ->addValue('Participant_Webform.Candidate_Payment', $contributionId)
          ->execute()
          ->first()['id'];

        $feesForThisEvent = $eventFees[$eventId] ?? NULL;
        $addedAmount = 0;
        // for fees payable now, we create additional line items in the contribution
        // and update the partipant_fee_amount and fee_level
        if ($feesForThisEvent) {
          $totalFeeForThisEvent = 0;
          $feeLevel = [];
          foreach ($feesForThisEvent as $fee) {
            $totalFeeForThisEvent += $fee['amount'];
            $feeLevel[] = $fee['label'];

            $params = [
              'entity_id' => $participantId,
              'entity_table' => 'civicrm_participant',
              'contribution_id' => $contributionId,
              'participant_count' => 1,
              // from getEventFees
              'price_field_value_id' => $fee['id'],
              'price_field_id' => $fee['price_field_id'],
              'qty' => 1,
              'unit_price' => $fee['amount'],
              'line_total' => $fee['amount'],
              'financial_type_id' => $fee['financial_type_id'],
              'label' => "CILB Candidate Registration - {$fee['label']}",
            ];
            \CRM_Price_BAO_LineItem::create($params);
            $addedAmount += $fee['amount'];
          }

          $updateAmount = $formFeeAmount - $addedAmount;
          \CRM_Core_DAO::executeQuery(<<<SQL
             UPDATE `civicrm_line_item`
           SET
             `unit_price` = {$updateAmount},
             `line_total` = {$updateAmount},
             `label` = 'Registration Form Fee'
           WHERE `id` = {$defaultLineItem['id']}
           SQL);

          $feeLevel = implode (', ', $feeLevel);

          \Civi\Api4\Participant::update(FALSE)
            ->addWhere('id', '=', $participantId)
            ->addValue('participant_fee_amount', $totalFeeForThisEvent)
            ->addValue('participant_fee_level', $feeLevel)
            ->execute();
        }

      }
      catch (\Exception $e) {
        \Drupal::logger('candidate_reg')->debug('Unable to register contact ID ' . $contactId . ' for event ID ' . $eventId . ' because ' . $e->getMessage());
        \Drupal::messenger()->addError($this->t('Sorry, we were unable to register you for this exam. Please contact the administrator at %adminEmail', [
          '%adminEmail' => \Drupal::config('system.site')->get('mail'),
        ]));
      }
    }

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
      $this->validateUniqueUser($form_state, $webform_submission);
    } elseif ($current_page == 'exam_fee_page') {
      $this->validateContributionAmount($form_state);
    } elseif ($current_page == 'user_identification') {
      $this->validateCandidateRep($form_state);
    } elseif ($current_page == 'payment_options') {
      $this->redirectPayByCheck($form_state);
    } elseif ($current_page == 'select_exam_page') {
      $this->validateParticipantStatus($form_state);
      $this->validateExamSelection($form_state);
    }

    // Backoffice registration
    if ($current_page == 'candidate_information') {
      $this->validateAgeReq($form_state);
      $this->validateSSNMatch($form_state);
      $this->validateUniqueUser($form_state, $webform_submission);
    } elseif ($current_page == 'exam_information') {
      $this->validateParticipantStatus($form_state);
      $this->validateExamSelection($form_state);
    } elseif ($current_page == 'payment_information') {
      $this->validateContributionAmount($form_state);
    }
  }

  private function validateExamSelection(FormStateInterface $formState)
  {
    $selectedEvents = $formState->getValue('select_exam_ids');
    if (!$selectedEvents) {
      $formState->setErrorByName('select_exam_ids', $this->t('You must select at least one exam'));
      return;
    }

    $events = \Civi\Api4\Event::get(FALSE)
      ->addSelect('Exam_Details.Exam_Part', 'Exam_Details.Exam_Part:label')
      ->addWhere('id', 'IN', $selectedEvents)
      ->execute();
    $eventsByPart = [];
    foreach ($events as $event) {
      if (empty($eventsByPart[$event['Exam_Details.Exam_Part']])) {
        $eventsByPart[$event['Exam_Details.Exam_Part']] = $event['id'];
      }
      else {
        $error_message = $this->t('You cannot select more then one %1 event', [1 => $event['Exam_Details.Exam_Part:label']]);
        $formState->setErrorByName('select_exam_ids', $error_message);
        break;
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

    $eventIds = $formState->getValue('select_exam_ids');

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
   * For a set of event IDs, get fees payable now
   *
   * Currently this gets price field amounts from price fields in the price set linked to the event
   *
   * NOTE 1: we assume one PriceFieldValue for each price field - any subsequent ones will be ignored
   * NOTE 2: we dont exclude an event having a pay now fee AND a pay layer fee
   *
   * @return array[] keys are event IDs, items are arrays of fee lines
   *   fee lines are records from PriceFieldValue table, keys include
   *   amount, label, price_field_id
   */
  private function getEventFeesPayableNow(array $eventIds): array {
    // NOTE: previously we used the exam format as a flag to determine
    // between events payable now and events payable later
    // BUT: now any events fees configured using Price Sets are pay now
    // pay later fees use a custom field
    //
    // $eventsPayableNow = (array) \Civi\Api4\Event::get(FALSE)
    //   ->addSelect('id')
    //   ->addWhere('Exam_Details.Exam_Format', '=', 'paper')
    //   ->addWhere('id', 'IN', $eventIds)
    //   ->execute()
    //   ->column('id');

    // fetch event titles
    $eventTitles = \Civi\Api4\Event::get(FALSE)
      ->addWhere('id', 'IN', $eventIds)
      ->addSelect('title')
      ->execute()
      ->indexBy('id')
      ->column('title');

    // remove any event ids that werent found in the db
    $eventIds = \array_keys($eventTitles);

    // fetch price sets
    $priceSetsByEventId = \Civi\Api4\PriceSetEntity::get(FALSE)
      ->addSelect('price_set_id', 'entity_id')
      ->addWhere('entity_table', '=', 'civicrm_event')
      ->addWhere('entity_id', 'IN', $eventIds)
      ->execute()
      ->indexBy('entity_id')
      ->column('price_set_id');

    $priceOptions = (array) \Civi\Api4\PriceFieldValue::get(FALSE)
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
      ->addOrderBy('id')
      ->execute()
      // NOTE: this will pick the first option for each field, and disregard
      // any subsequent options
      ->indexBy('price_field_id');

    $fees = [];

    // for each event, pick all options from the corresponding price set
    // NOTE: usually there is just one option
    foreach ($priceSetsByEventId as $eventId => $priceSetId) {
      $fees[$eventId] = array_filter($priceOptions, function ($option) use ($priceSetId) {
        return ($option['price_field_id.price_set_id'] === $priceSetId);
      });

      // prepend event title to line item labels
      $eventTitle = $eventTitles[$eventId];
      foreach ($fees[$eventId] as &$fee) {
        $fee['label'] = "{$eventTitle} - {$fee['label']}";
      }
    }

    return $fees;
  }

  /**
   * For a set of event IDs, get event fees payable later
   *
   * For most events, the fee will be provided by the CustomField External_Fee field
   * on the event. The amount will be shown to users at checkout, but no line item will be created
   *
   * Note: we dont exclude an event having a pay now fee AND a pay layer fee
   *
   * @return array[] keys are event IDs, items are arrays of fee lines
   */
  private function getEventFeesPayableLater(array $eventIds): array {
    $events = (array) \Civi\Api4\Event::get(FALSE)
      ->addSelect('id', 'title', 'Exam_Details.External_Fee_Amount', 'Exam_Details.External_Fee_Description')
      ->addWhere('id', 'IN', $eventIds)
      ->execute();

    $fees = [];

    foreach ($events as $event) {
      $fees[$event['id']] = [];

      if ($event['Exam_Details.External_Fee_Amount']) {
        $fee = [
          'label' => $event['title'],
          'amount' => $event['Exam_Details.External_Fee_Amount'],
        ];
        if ($event['Exam_Details.External_Fee_Description']) {
          $fee['label'] .= ' - ' . $event['Exam_Details.External_Fee_Description'];
        }
        $fees[$event['id']][] = $fee;
      }
    }

    return $fees;
  }

  private function getFormFeeAmount(): int {
    return \Civi\Api4\PriceFieldValue::get(FALSE)
      ->addSelect('amount')
      ->addWhere('price_field_id.price_set_id.name', '=', 'Registration_Form_Fee')
      ->execute()
      ->first()['amount'] ?? 135;
  }

  /**
   * Get the total amount payable now
   */
  private function getPayableNowAmount($eventIds): int {
    $eventTotal = 0;

    // loop through all event fees payable now
    foreach ($this->getEventFeesPayableNow($eventIds) as $eventId => $fees) {
      foreach ($fees as $fee) {
        $eventTotal += $fee['amount'];
      }
    }

    $formFee = $this->getFormFeeAmount();

    return $eventTotal + $formFee;
  }

  /**
   * Generates a pseduo-random transaction id starting with 99. It is guaranteed to be unique in the database
   */
  private function generateTransactionId(): string {
    do {
      $trxnId = '99';
      foreach (\range(1, 10) as $i) {
        $trxnId .= (string) \random_int(0, 9);
      }
      $alreadyUsed = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('id')
        ->addWhere('trxn_id', '=', $trxnId)
        ->execute()
        ->count();
    } while ($alreadyUsed);
    return $trxnId;
  }

  protected function getRegistrantContact($form, $form_state) {
    if ($form['#webform_id'] === 'backoffice_registration') {
      // never use the logged in contact
      // but check selected existing contact
      return (int) $form_state->getValue('civicrm_1_contact_1_contact_existing');
    }
    return CRM_Core_Session::getLoggedInContactID();
  }

  protected function setCategoryOptions(&$form, $form_state) {
    $elements = WebformFormHelper::flattenElements($form);
    if (isset($elements['select_category_id'])) {
      // populate exam category selector based on available events
      $events = $this->getEventRegistrationOptions($form, $form_state);
      $allCategories = $this->getAllExamCategories();
      $categories_to_hide = \Civi::settings()->get('cilb_exam_registration_hidden_categories');
      if (!empty($categories_to_hide)) {
        $categories_to_hide = json_decode($categories_to_hide, TRUE);
      }
      else {
        $categories_to_hide = [];
      }
      $categories = $bfExamIds = [];
      $genericBusinessAndFinanceExamCategoryId = self::getBusinessAndFinanceExam()->first()['event_type_id'] ?? NULL;
      foreach ($allCategories as $categoryId => $categoryLabel) {
        if (in_array($categoryId, $categories_to_hide)) {
          continue;
        }
        $categories[(string) $categoryId] = $categoryLabel;
        $categoryBusinessandFiannceExamCategoryId = self::getBusinessAndFinanceExam($categoryId);
        if (count($categoryBusinessandFiannceExamCategoryId) < 1) {
          if ($genericBusinessAndFinanceExamCategoryId) {
            $bfExamIds[$categoryId] = $genericBusinessAndFinanceExamCategoryId;
          }
        }
        else {
          $bfExamIds[$categoryId] = $categoryBusinessandFiannceExamCategoryId->first()['event_type_id'];
        }
        $categoryInEventsArray = FALSE;
        foreach ($events as $event) {
          // Now loop through all the Events that the person can register for to see if there is either an event with the exam category or there is it's business and finance exam present.
          if (!$categoryInEventsArray && ($event['event_type_id'] == $categoryId || $event['event_type_id'] == $bfExamIds[$categoryId])) {
            // If we have found an event flag it it should be in the array.
            $categoryInEventsArray = TRUE;
          }
        }
        // If the event category and its Business and Finance Event Category do not exist in the events array we need to remove it from the categories to select.
        if (!$categoryInEventsArray) {
          unset($categories[$categoryId]);
        }

      }
      asort($categories);
      $form['#attached']['drupalSettings']['cilbBFEventMap'] = $bfExamIds;
      $elements['select_category_id']['#options'] = $categories;
    }
  }

  protected function setEventOptions(&$form, $form_state, ?int $categoryFilter = NULL) {
    $events = $this->getEventRegistrationOptions($form, $form_state, $categoryFilter);

    $events = array_map(function ($event) {
      // provide data and location for non-BF plumbing.
      // TODO: use in person / external field
      if (
        ($event['event_type_id:name'] === 'Plumbing')
        && ($event['Exam_Details.Exam_Part'] !== 'BF')
      ) {
        $event['label'] = implode(' - ', array_filter([
          $event['title'],
          $event['loc_block_id.address_id.city'],
          date('Y-m-d', \strtotime($event['start_date'])),
        ]));

        $addressPartKeys = [
          'loc_block_id.address_id.street_address',
          'loc_block_id.address_id.supplemental_address_1',
          'loc_block_id.address_id.supplemental_address_2',
          'loc_block_id.address_id.city',
          'loc_block_id.address_id.postal_code',
          'loc_block_id.address_id.state_province_id:abbr',
          'loc_block_id.address_id.country_id:label',
        ];
        $addressParts = array_filter(array_map(fn($key) => \strip_tags($event[$key] ?? ''), $addressPartKeys));
        $event['address'] = implode('<br />', $addressParts);
        return $event;
      }
      // for all other events, just use title
      $event['label'] = $event['title'];
      $event['address'] = NULL;
      return $event;
    }, $events);

    // pass event info to javascript for filtering etc
    $form['#attached']['drupalSettings']['cilbEventOptions'] = $events;

    // set form element options for validation
    $elements = WebformFormHelper::flattenElements($form);
    if (isset($elements['select_exam_ids'])) {
      $elements['select_exam_ids']['#options'] = array_map(fn ($event) => $event['label'], $events);
    }
  }

  /**
   * @return int[] array of event ids for valid registration options
   */
  protected function getEventRegistrationOptions($form, $form_state, ?int $categoryFilter = NULL): array {
    // Fetch events with space remaining or no space cap
    $eventFetch = \Civi\Api4\Event::get(FALSE)
      ->addSelect(
        'id',
        'title',
        'Exam_Details.Exam_Part',
        'event_type_id',
        'event_type_id:name',
        'event_type_id:label',
        'start_date',
	'end_date',
        'loc_block_id.address_id.street_address',
        "loc_block_id.address_id.supplemental_address_1",
        "loc_block_id.address_id.supplemental_address_2",
        "loc_block_id.address_id.city",
        "loc_block_id.address_id.state_province_id:abbr",
        "loc_block_id.address_id.country_id:label",
        "loc_block_id.address_id.postal_code"
      )
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_online_registration', '=', TRUE)
      ->addWhere('is_template', '!=', TRUE)
      //->addWhere('start_date', '>', 'now')
      ->addClause('OR', ['max_participants', 'IS NULL'], ['remaining_participants', '>', 0]);

    // for frontend form, we need to limit based on category selection on
    // earlier page when we go to later page
    // BUT not when flicking back to the category selector
    if ($categoryFilter) {
      $eventFetch->addWhere('event_type_id', '=', $categoryFilter);
    }

    $events = (array) $eventFetch
      ->execute()
      ->indexBy('id');

    $examFound = FALSE;
    $categoryTitle = NULL;
    if ($categoryFilter) {
      $categoryTitle = OptionValue::get(FALSE)
        ->addWhere('option_group_id:name', '=', 'event_type')
        ->addWhere('value', '=', $categoryFilter)
        ->execute()->first()['label'] ?? NULL;
      $businessAndFinanceExam = self::getBusinessAndFinanceExam($categoryFilter);
      if (count($businessAndFinanceExam) > 0) {
        $examFound = TRUE;
        $bfExam = $businessAndFinanceExam->first();
        $bfExam['title'] = $categoryTitle . ' - Business and Finance';
        $events[$bfExam['id']] = $bfExam;
      }
    }
    if (!$examFound) {
      if ($form['#webform_id'] !== 'backoffice_registration') {
        $businessAndFinanceExam = self::getBusinessAndFinanceExam();
        $bfExam = $businessAndFinanceExam->first();
        if ($bfExam) {
          $bfExam['title'] = $categoryTitle ? "{$categoryTitle} - Business and Finance" : "Business and Finance";
          $events[$bfExam['id']] = $bfExam;
        }
      }
      else {
        $businessAndFinanceExam = self::getBusinessAndFinanceExam(NULL, TRUE);
        $businessAndFinanceExams = $businessAndFinanceExam->getArrayCopy();
        foreach ($businessAndFinanceExams as $bfExam) {
          $events[$bfExam['id']] = $bfExam;
        }
      }
    }

    // if existing contact, check for existing registrations that mean we cant reregister for an exam
    $contactId = $this->getRegistrantContact($form, $form_state);
    if ($contactId) {
      $events = self::filterRegisterableEventsForContact($events, $contactId, $categoryFilter);
    }

    // exclude Plumbing TK exams that are in the past
    $events = array_filter($events, function ($event) {
      if (
        ($event['event_type_id:name'] === "Plumbing")
        && ($event['Exam_Details.Exam_Part'] === 'TK')
        && (!empty($event['end_date']) && date('Ymd', strtotime($event['end_date'])) < date('Ymd'))
      ) {
        return FALSE;
      }
      return TRUE;
    });

    return $events;
  }

  /**
   * NOTES:
   * - previous registrations where the result is FAIL or CANCELLED are excluded (need to be able to retake)
   * - previous registrations where the admin has set Bypass are excluded (this is used for cases
   *   where e.g. candidates need to retake after 3 years)
   * - the logic is different depending on whether the Exam Part is "Category Specific":
   *     category specific => only exclude registration is for the same part AND same category
   *     otherwise => exclude if registration for any exam for the same part, regardless of category
   *
   */
  protected function filterRegisterableEventsForContact(array $events, int $contactId, $selectedCategory): array {
    $exclusions = [];

    $examPartCategorySpecificity = (array) \Civi\Api4\OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'Exam_Part')
      ->addSelect('value', 'Exam_Part_Options.Category_Specific')
      ->execute()
      ->indexBy('value')
      ->column('Exam_Part_Options.Category_Specific');

    $previousRegistrations = (array) \Civi\Api4\Participant::get(FALSE)
      ->addSelect('event_id.Exam_Details.Exam_Part', 'event_id.event_type_id', 'event_id', 'status_id:name')
      ->addJoin('Event AS event', 'INNER', ['event.id', '=', 'event_id'])
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('status_id:name', 'NOT IN', ['Fail', 'Cancelled'])
      ->addWhere('Candidate_Result.Bypass_Reregistration_Check', 'IS EMPTY')
      ->execute()
      ->indexBy('event_id');

    $businessAndFinanceCategoryId = 0;
    if (!empty($selectedCategory)) {
      $businessAndFinanceExam = self::getBusinessAndFinanceExam($selectedCategory);
      if (count($businessAndFinanceExam) < 1) {
        $businessAndFinanceExam = self::getBusinessAndFinanceExam();
      }
      $businessAndFinanceCategoryId = $businessAndFinanceExam->first()['event_type_id'];
    }

    foreach ($previousRegistrations as $previousReg) {
      // part = Trade Knowledge or Business & Finance or ...
      $previousRegPart = $previousReg['event_id.Exam_Details.Exam_Part'];

      if ($examPartCategorySpecificity[$previousRegPart]) {
        // if registrations for this part are category specific,
        // exclude events that match the Part AND Category
        $exclusions[] = [
          // part
          'Exam_Details.Exam_Part' => $previousReg['event_id.Exam_Details.Exam_Part'],
          // category
          'event_type_id' => $previousReg['event_id.event_type_id'],
        ];
      }
      elseif (!empty($businessAndFinanceCategoryId)  && in_array($previousReg['event_id.event_type_id'], self::getBusinessAndFiannceExamCategories())) {
        // We have a previous registration of a Business and Finance Exam and we need to figure out if its the one for the currently selected category
        // If it is then we use that event type id as part of the exlusions. Otherwise there should be no exclusion filter applied.
        if ($businessAndFinanceCategoryId != $previousReg['event_id.event_type_id']) {
          continue;
        }
        $exclusions[] = [
          'event_type_id' => $previousReg['event_id.event_type_id'],
        ];
      }
      elseif (in_array($previousReg['event_id.event_type_id'], self::getBusinessAndFiannceExamCategories())) {
        $exclusions[] = [
          'event_type_id' => $previousReg['event_id.event_type_id'],
        ];
      }
      elseif ($previousRegPart !== 'BF') {
        // events only need to match the part to be excluded
        $exclusions[] = [
          'Exam_Details.Exam_Part' => $previousRegPart,
        ];
      }
    }

    // apply our exclusions to the event list
    return array_filter($events, function ($event) use ($exclusions) {
      foreach ($exclusions as $exclusion) {
        if (self::exclusionMatch($event, $exclusion)) {
          return FALSE;
        }
      }
      return TRUE;
    });
  }

  private static function exclusionMatch($event, $exclusion) {
    foreach ($exclusion as $key => $value) {
      if ($event[$key] !== $value) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private static function getBusinessAndFinanceExam($selectedCategory = NULL, $isBackoffice = FALSE): \Civi\Api4\Generic\Result {
    $businessAndFinanceExams = \Civi\Api4\Event::get(FALSE)
      ->addSelect(
        'id',
        'title',
        'Exam_Details.Exam_Part',
        'event_type_id',
        'event_type_id:name',
        'event_type_id:label',
        'start_date',
        'loc_block_id.address_id.street_address',
        "loc_block_id.address_id.supplemental_address_1",
        "loc_block_id.address_id.supplemental_address_2",
        "loc_block_id.address_id.city",
        "loc_block_id.address_id.state_province_id:abbr",
        "loc_block_id.address_id.country_id:label",
        "loc_block_id.address_id.postal_code"
      )
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('is_online_registration', '=', TRUE)
      ->addWhere('is_template', '!=', TRUE)
      //->addWhere('start_date', '>', 'now')
      ->addClause('OR', ['max_participants', 'IS NULL'], ['remaining_participants', '>', 0])
      ->addWhere('event_type_id', 'IN', self::getBusinessAndFiannceExamCategories());
    if (!empty($selectedCategory)) {
      $businessAndFinanceExams->addWhere('Exam_Details.Exam_Category_this_exam_applies_to', '=', $selectedCategory);
    }
    elseif (!$isBackoffice) {
      $businessAndFinanceExams->addWhere('Exam_Details.Exam_Category_this_exam_applies_to', 'IS EMPTY');
    }
    return $businessAndFinanceExams->execute();
  }

  private static function getBusinessAndFiannceExamCategories(): array {
    $eventCategories = \Civi::entity('Event')->getOptions('event_type_id', [], TRUE);
    $bfCategories = [];
    foreach ($eventCategories as $eventCategory) {
      if (str_contains($eventCategory['name'], 'Business and Finance')) {
        $bfCategories[] = $eventCategory['id'];
      }
    }
    return $bfCategories;
  }

  private static function getAllExamCategories(): array {
    $eventCategories = \Civi::entity('Event')->getOptions('event_type_id', [], TRUE);
    $categories = [];
    foreach ($eventCategories as $eventCategory) {
      $categories[$eventCategory['id']] = $eventCategory['label'];
    }
    return $categories;
  }

}
