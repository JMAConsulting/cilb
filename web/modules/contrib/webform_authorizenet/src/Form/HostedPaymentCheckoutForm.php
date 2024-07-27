<?php

namespace Drupal\webform_authorizenet\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform_authorizenet\Utility\SubmissionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HostedPaymentCheckoutForm.
 *
 * Provides hosted payment checkout form that passes payment to
 * authorize.net service.
 */
class HostedPaymentCheckoutForm extends FormBase {

  /**
   * The webform token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->tokenManager = $container->get('webform.token_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hosted_payment_checkout_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $checkout_form_data = NULL) {
    try {
      $parameters = Json::decode(base64_decode(urldecode($checkout_form_data)));
    }
    catch (\Throwable $throwable) {
      $parameters = NULL;
    }

    if (!is_array($parameters)) {
      // Redirect to front page.
      $response = new TrustedRedirectResponse(Url::fromRoute('<front>')->toString());
      $cacheable_metadata = new CacheableMetadata();
      $cacheable_metadata->setCacheMaxAge(0);
      $response->addCacheableDependency($cacheable_metadata);
      throw new EnforcedResponseException($response);
    }

    $webform_submission = WebformSubmission::load($parameters['webform_submission_id']);
    if (!$webform_submission) {
      // Redirect to front page.
      $response = new TrustedRedirectResponse(Url::fromRoute('<front>')->toString());
      $cacheable_metadata = new CacheableMetadata();
      $cacheable_metadata->setCacheMaxAge(0);
      $response->addCacheableDependency($cacheable_metadata);
      throw new EnforcedResponseException($response);
    }

    $webform = $webform_submission->getWebform();
    $handler_settings = SubmissionHelper::getHandlerConfiguration($webform_submission);

    $token_options['clear'] = TRUE;
    $title_value = $handler_settings['settings']['checkout_form_title'] ?? '';
    $title_value = $this->tokenManager->replaceNoRenderContext($title_value, $webform_submission, [], $token_options);
    $content_value = $handler_settings['settings']['checkout_form_content']['value'] ?? '';
    $content_value = $this->tokenManager->replaceNoRenderContext($content_value, $webform_submission, [], $token_options);
    $submit_label = $handler_settings['settings']['checkout_form_submit_label'] ?? '';
    $submit_label = $this->tokenManager->replaceNoRenderContext($submit_label, $webform_submission, [], $token_options);

    $form['#title'] = $title_value;

    $form['content'] = [
      '#type' => 'processed_text',
      '#text' => $content_value,
      '#format' => $handler_settings['settings']['checkout_form_content']['format'] ?? '',
    ];

    // Authorize.net token.
    $form['token'] = [
      '#type' => 'hidden',
      '#value' => $parameters['token'],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $submit_label,
      '#button_type' => 'primary',
    ];

    // Form data will be sent to Authorize.net for payment processing.
    $form['#action'] = $parameters['endpoint'];

    $form['#attributes']['class'][] = $webform->id();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Do nothing here, since Authorize.net will process the submission.
  }

}
