<?php

namespace Drupal\webform_authorizenet\Plugin\WebformHandler;

use Drupal\webform_authorizenet\Utility\PaymentStatus;
use Drupal\webform_authorizenet\Utility\SubmissionHelper;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use net\authorize\api\constants\ANetEnvironment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Webform submission webform_authorizenet handler.
 *
 * @WebformHandler(
 *   id = "webform_authorizenet",
 *   label = @Translation("Webform Authorize.Net Handler"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to Authorize.Net."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class AuthorizeNetHandler extends WebformHandlerBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * A mail manager for sending email.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * The webform theme manager.
   *
   * @var \Drupal\webform\WebformThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The webform element plugin manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * Cache of default configuration values.
   *
   * @var array
   */
  protected $defaultValues;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->currentUser = $container->get('current_user');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->languageManager = $container->get('language_manager');
    $instance->mailManager = $container->get('plugin.manager.mail');
    $instance->themeManager = $container->get('webform.theme_manager');
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->elementManager = $container->get('plugin.manager.webform.element');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];

    $settings['payment_mode'] = $this->configuration['payment_mode'];
    $settings['accept_hosted_url'] = $this->getEndpoint();
    $settings['transaction_type'] = $this->getSupportedTransactionTypesDefinitions()[$this->configuration['transaction_type']]['label'] ?? '';
    $settings['item_price'] = $this->configuration['item_price'];
    $settings['number_of_items'] = $this->configuration['number_of_items'] ?: 1;

    return [
      '#settings' => $settings,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_login_id' => '',
      'auth_key' => '',
      'payment_mode' => 'sandbox',
      'transaction_type' => 'auth_only',
      'item_price' => 0,
      'number_of_items' => '',
      'customer_email' => '',
      'bill_to_first_name' => '',
      'bill_to_last_name' => '',
      'bill_to_address' => '',
      'bill_to_city' => '',
      'bill_to_state' => '',
      'bill_to_zip' => '',
      'bill_to_phone' => '',
      'checkout_form_title' => 'Almost there',
      'checkout_form_content' => [
        'value' => '<p>Your total amount is <strong>[webform_submission:webform_authorizenet_total_amount] USD</strong>.</p><p>Upon clicking "Pay" you will be securely redirected to our trusted payment partner, Authorize.net, to complete your transaction.</p>',
        'format' => 'full_html',
      ],
      'checkout_form_submit_label' => 'Pay',
      'payment_done_message' => [
        'value' => '<p>Congratulations! Your payment was successful.</p>',
        'format' => 'full_html',
      ],
    ];
  }

  /**
   * Build a select with webform elements.
   *
   * @param string $name
   *   The element's key.
   * @param string $title
   *   The element's title.
   * @param string $description
   *   The element's description.
   * @param bool $required
   *   TRUE if the element is required.
   * @param array $element_options
   *   The element options.
   *
   * @return array
   *   A select other element.
   */
  protected function buildElement($name, $title, $description, $required = FALSE, array $element_options = []) {
    $options = [];
    if (!empty($element_options)) {
      $options[(string) $this->t('Elements')] = $element_options;
    }

    $element[$name] = [
      '#type' => 'select',
      '#title' => $title,
      '#description' => $description,
      '#options' => $options,
      '#empty_option' => (!$required) ? $this->t('- None -') : NULL,
      '#required' => $required,
      '#default_value' => $this->configuration[$name],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->applyFormStateToConfiguration($form_state);

    $name_element_options = [];
    $elements = $this->webform->getElementsInitializedAndFlattened();
    foreach ($elements as $element_key => $element) {
      $element_plugin = $this->elementManager->getElementInstance($element);
      if (!$element_plugin->isInput($element) || !isset($element['#type'])) {
        continue;
      }

      // Set title.
      $element_title = (isset($element['#title'])) ? new FormattableMarkup('@title (@key)', ['@title' => $element['#title'], '@key' => $element_key]) : $element_key;

      // Multiple value elements can NOT be used as a tokens.
      if ($element_plugin->hasMultipleValues($element)) {
        continue;
      }

      if (!$element_plugin->isComposite()) {
        // Add name element token.
        $name_element_options["[webform_submission:values:$element_key:raw]"] = $element_title;
      }

      // Element type specific tokens.
      // Allow 'webform_name' composite to be used a value token.
      if ($element['#type'] === 'webform_name') {
        $name_element_options["[webform_submission:values:$element_key:value]"] = $element_title;
      }

      // Handle composite sub elements.
      if ($element_plugin instanceof WebformCompositeBase) {
        $composite_elements = $element_plugin->getCompositeElements();
        foreach ($composite_elements as $composite_key => $composite_element) {
          $composite_element_plugin = $this->elementManager->getElementInstance($element);
          if (!$composite_element_plugin->isInput($element) || !isset($composite_element['#type'])) {
            continue;
          }

          // Set composite title.
          if (isset($element['#title'])) {
            $f_args = [
              '@title' => $element['#title'],
              '@composite_title' => $composite_element['#title'],
              '@key' => $element_key,
              '@composite_key' => $composite_key,
            ];
            $composite_title = new FormattableMarkup('@title: @composite_title (@key: @composite_key)', $f_args);
          }
          else {
            $composite_title = "$element_key:$composite_key";
          }

          // Add name element token. Only applies to basic (not composite) elements.
          $name_element_options["[webform_submission:values:$element_key:$composite_key:raw]"] = $composite_title;
        }
      }
    }

    // API Credentials.
    $form['api_credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Authorize.Net API Credentials'),
      '#open' => TRUE,
    ];
    $form['api_credentials']['api_login_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Login ID'),
      '#description' => $this->t('Enter your Authorize.Net login id.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['api_login_id'],
    ];
    $form['api_credentials']['auth_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Transaction Key'),
      '#description' => $this->t('Enter your Authorize.Net auth key.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['auth_key'],
    ];
    $form['api_credentials']['payment_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Payment Mode'),
      '#description' => $this->t('The Payment Mode indicates the method through which payments will be processed. Make sure you use correct credentials.'),
      '#options' => ['sandbox' => $this->t('Sandbox'), 'production' => $this->t('Production')],
      '#default_value' => $this->configuration['payment_mode'],
    ];

    // Transaction.
    $form['transaction'] = [
      '#type' => 'details',
      '#title' => $this->t('Payment Transaction'),
      '#open' => TRUE,
    ];
    $required_fields = ['anet_payment_status', 'anet_transaction_reference'];
    $form['transaction']['fields_info'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>Ensure that the webform has the fields with the following machine names: @fields. These fields must be able to store string values for transaction processing by the handler. Make sure these fields are not changeable by users. For instance, you can use webform element "Text field" with disabled Create and Update accesses and View access for admin only. These fields should be added manually by administrator.</strong>', [
        '@fields' => implode(', ', $required_fields),
      ]),
    ];
    $transaction_types = array_column($this->getSupportedTransactionTypesDefinitions(), 'label', 'name');
    $form['transaction']['transaction_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Transaction Type'),
      '#description' => $this->t('The transaction type to use while creating transactions on Authorize.net'),
      '#required' => TRUE,
      '#options' => $transaction_types,
      '#default_value' => $this->configuration['transaction_type'],
    ];
    $form['transaction']['item_price'] = [
      '#type' => 'number',
      '#title' => $this->t('Item Price'),
      '#description' => $this->t('Enter the price of a single selling item.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['item_price'],
      '#min' => 0,
      '#step' => 0.01,
    ];
    $form['transaction']['number_of_items'] = $this->buildElement('number_of_items', $this->t('Number of Items'), $this->t('The webform element that will contain number of items to sell. Leave empty to use one item.'), FALSE, $name_element_options);

    // Customer information.
    $form['customer'] = [
      '#type' => 'details',
      '#title' => $this->t('Authorize.net Customer Information'),
      '#description' => $this->t('The information will be passed to the Authorize.net Hosted Page as the Customer Information.'),
      '#open' => TRUE,
    ];
    $form['customer']['customer_email'] = $this->buildElement(
      'customer_email',
      $this->t('Email'),
      $this->t('The webform element that will contain customer email address.'),
      TRUE,
      $name_element_options
    );

    // Bill To information.
    $form['bill_to'] = [
      '#type' => 'details',
      '#title' => $this->t('Authorize.net Bill To Information'),
      '#description' => $this->t('The information will be passed to the Authorize.net Hosted Page as the Billing Address. You can leave it empty to skip populating values to Authorize.net.'),
      '#open' => TRUE,
    ];
    $form['bill_to']['bill_to_first_name'] = $this->buildElement(
      'bill_to_first_name',
      $this->t('First Name'),
      $this->t('The webform element that will contain customer first name.'),
      FALSE,
      $name_element_options
    );
    $form['bill_to']['bill_to_last_name'] = $this->buildElement(
      'bill_to_last_name',
      $this->t('Last Name'),
      $this->t('The webform element that will contain customer last name.'),
      FALSE,
      $name_element_options
    );
    $form['bill_to']['bill_to_address'] = $this->buildElement(
      'bill_to_address',
      $this->t('Address'),
      $this->t('The webform element that will contain customer address.'),
      FALSE,
      $name_element_options
    );
    $form['bill_to']['bill_to_city'] = $this->buildElement(
      'bill_to_city',
      $this->t('City'),
      $this->t('The webform element that will contain customer city.'),
      FALSE,
      $name_element_options
    );
    $form['bill_to']['bill_to_state'] = $this->buildElement(
      'bill_to_state',
      $this->t('State'),
      $this->t('The webform element that will contain customer state.'),
      FALSE,
      $name_element_options
    );
    $form['bill_to']['bill_to_zip'] = $this->buildElement(
      'bill_to_zip',
      $this->t('Zip'),
      $this->t('The webform element that will contain customer zip code.'),
      FALSE,
      $name_element_options
    );
    $form['bill_to']['bill_to_phone'] = $this->buildElement(
      'bill_to_phone',
      $this->t('Phone Number'),
      $this->t('The webform element that will contain customer phone number.'),
      FALSE,
      $name_element_options
    );

    // Checkout form.
    $form['checkout'] = [
      '#type' => 'details',
      '#title' => $this->t('Checkout Form'),
      '#open' => TRUE,
    ];
    $form['checkout']['checkout_form_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['checkout_form_title'],
    ];
    $form['checkout']['checkout_form_content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Content'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['checkout_form_content']['value'] ?? '',
      '#format' => $this->configuration['checkout_form_content']['format'] ?? filter_default_format(),
    ];
    $form['checkout']['checkout_form_submit_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Submit Label'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['checkout_form_submit_label'],
    ];

    // After payment.
    $form['after_payment'] = [
      '#type' => 'details',
      '#title' => $this->t('After Payment'),
      '#open' => TRUE,
    ];
    $form['after_payment']['payment_done_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Payment Done Message'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['payment_done_message']['value'] ?? '',
      '#format' => $this->configuration['payment_done_message']['format'] ?? filter_default_format(),
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $webform_state = $webform_submission->getWebform()->getSetting('results_disabled') ? WebformSubmissionInterface::STATE_COMPLETED : $webform_submission->getState();
    $this->authorizeNetPost($webform_state, $webform_submission);
  }

  /**
   * Execute a remote post.
   *
   * @param string $webform_state
   *   The state of the webform submission.
   *   Either STATE_NEW, STATE_DRAFT, STATE_COMPLETED, STATE_UPDATED, or
   *   STATE_CONVERTED depending on the last save operation performed.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission to be posted.
   */
  protected function authorizeNetPost($webform_state, WebformSubmissionInterface $webform_submission) {
    if ($webform_state === 'completed') {
      $data = $webform_submission->getData();
      try {
        $payment_page = $this->getAnAcceptPaymentPage($webform_submission);
        $token = $payment_page->getToken();

        if ($token) {
          if (array_key_exists('anet_payment_status', $data)) {
            SubmissionHelper::updatePaymentStatus($webform_submission->id(), PaymentStatus::PENDING);
          }

          $payment_form_data = [
            'token' => $token,
            'endpoint' => $this->getEndpoint(),
            'webform_submission_id' => $webform_submission->id(),
          ];
          $payment_form_data = urlencode(base64_encode(Json::encode($payment_form_data)));
          $url = new Url('webform_authorizenet.hosted_payment_checkout_form', ['checkout_form_data' => $payment_form_data]);

          $redirect = new RedirectResponse($url->toString());
          $redirect->send();
        }
        else {
          $this->messenger()->addWarning($this->t('Unable to prepare checkout page. We should be back shortly. Thank you for your patience.'));
          $logger = \Drupal::logger('webform_authorizenet');
          $logger->error("The webform submission @id is incomplete. Authorize.net handler has stopped payment processing. The reason is there is no authorize.net token for processing.", ['@id' => $webform_submission->id()]);
        }
      }
      catch (\Exception $e) {
        watchdog_exception('webform_authorizenet', $e);
      }
    }
  }

  /**
   * Provides endpoint link.
   *
   * @return string
   */
  public function getEndpoint() {
    if ($this->configuration['payment_mode'] === 'production') {
      return 'https://secure.authorize.net/payment/payment';
    }

    return 'https://test.authorize.net/payment/payment';
  }

  /**
   * Provides payment information.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission.
   *
   * @return AnetAPI\AnetApiResponseType
   */
  function getAnAcceptPaymentPage(WebformSubmissionInterface $webform_submission) {
    // Get handler settings with replaced token from submission.
    $configuration = SubmissionHelper::getConfigurationWithSubmissionContext($this->configuration, $webform_submission);

    // Get webform submission values.
    $data = $webform_submission->getData();

    // Set the transaction's refId for webform_submission entity.
    $refId = 'ref' . time();
    if (array_key_exists('anet_transaction_reference', $data)) {
      SubmissionHelper::updateTransactionReference($webform_submission->id(), $refId);
    }

    // Create a merchantAuthenticationType object with authentication details
    // retrieved from the constants file.
    $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
    $merchantAuthentication->setName($configuration['api_login_id']);
    $merchantAuthentication->setTransactionKey($configuration['auth_key']);

    $transaction_type = $configuration['transaction_type'];
    $anet_transaction_type = $this->getSupportedTransactionTypesDefinitions()[$transaction_type]['transaction_type'];

    $amount = '[webform_submission:webform_authorizenet_total_amount]';
    $token_options['clear'] = TRUE;
    $amount = $this->replaceTokens($amount, $webform_submission, [], $token_options);

    // Create a transaction.
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType($anet_transaction_type);
    $transactionRequestType->setAmount($amount);

    // Set hosted form options.
    $setting1 = new AnetAPI\SettingType();
    $setting1->setSettingName('hostedPaymentButtonOptions');
    $setting1->setSettingValue(Json::encode(['text' => 'Pay']));

    $setting2 = new AnetAPI\SettingType();
    $setting2->setSettingName('hostedPaymentOrderOptions');
    $setting2->setSettingValue(Json::encode(['show' => FALSE]));

    $setting3 = new AnetAPI\SettingType();
    $setting3->setSettingName('hostedPaymentReturnOptions');

    $url_settings = [
      'url' => Url::fromRoute('webform_authorizenet.validation', ['sid' => $webform_submission->id()], ['absolute' => TRUE, 'query' => ['tid' => $refId]])->toString(),
      'cancelUrl' => Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString(),
      'showReceipt' => TRUE,
    ];
    $setting3->setSettingValue(Json::encode($url_settings));

    // Build transaction request.
    $request = new AnetAPI\GetHostedPaymentPageRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($refId);
    $request->addToHostedPaymentSettings($setting1);
    $request->addToHostedPaymentSettings($setting2);
    $request->addToHostedPaymentSettings($setting3);

    // Customer info.
    $customer = new AnetAPI\CustomerDataType();
    $customer->setEmail($configuration['customer_email']);

    // Bill To.
    $billto = new AnetAPI\CustomerAddressType();
    $billto->setFirstName($configuration['bill_to_first_name']);
    $billto->setLastName($configuration['bill_to_last_name']);
    $billto->setAddress($configuration['bill_to_address']);
    $billto->setCity($configuration['bill_to_city']);
    $billto->setState($configuration['bill_to_state']);
    $billto->setZip($configuration['bill_to_zip']);
    $billto->setPhoneNumber($configuration['bill_to_phone']);

    $transactionRequestType->setCustomer($customer);
    $transactionRequestType->setBillTo($billto);

    $request->setTransactionRequest($transactionRequestType);

    // Execute request.
    $controller = new AnetController\GetHostedPaymentPageController($request);
    if ($this->configuration['payment_mode'] === 'production') {
      $response = $controller->executeWithApiResponse(ANetEnvironment::PRODUCTION);
    }
    else {
      $response = $controller->executeWithApiResponse(ANetEnvironment::SANDBOX);
    }

    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
      // Do nothing here.
    }
    else {
      $logger = \Drupal::logger('webform_authorizenet');
      $error_messages = $response->getMessages()->getMessage();
      $logger->error("Failed to get hosted payment page token for @id submission. API response: @code - @text", [
        '@id' => $webform_submission->id(),
        '@code' => $error_messages[0]->getCode(),
        '@text' => $error_messages[0]->getText(),
      ]);
    }

    return $response;
  }

  /**
   * Gets the supported transaction types definitions.
   *
   * @return array
   *   The supported transaction types definitions.
   */
  protected function getSupportedTransactionTypesDefinitions(): array {
    return [
      'auth_capture' => [
        'name' => 'auth_capture',
        'label' => $this->t('Authorization and Capture'),
        'transaction_type' => 'authCaptureTransaction',
      ],
      'auth_only' => [
        'name' => 'auth_only',
        'label' => $this->t('Authorization Only'),
        'transaction_type' => 'authOnlyTransaction',
      ],
    ];
  }

}
