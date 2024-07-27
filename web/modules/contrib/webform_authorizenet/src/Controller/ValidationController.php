<?php

namespace Drupal\webform_authorizenet\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\webform_authorizenet\Utility\PaymentStatus;
use Drupal\webform_authorizenet\Utility\SubmissionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Render\Markup;

/**
 * Class ValidationController
 *
 * @package Drupal\webform_authorizenet\Controller
 */
class ValidationController extends ControllerBase {

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
   * Validates payment by GET request.
   *
   * @param $sid
   *   The webform submission id.
   * @param Request $request
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function validateId($sid, Request $request) {
    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $this->entityTypeManager()->getStorage('webform_submission')->load($sid);

    if ($submission) {
      $data = $submission->getData();
      if (array_key_exists('anet_payment_status', $data) && array_key_exists('anet_transaction_reference', $data)) {
        $tid = $data['anet_transaction_reference'];
        $refId = $request->query->get('tid');
        if ($tid === $refId) {
          SubmissionHelper::updatePaymentStatus($sid, PaymentStatus::SUCCESS);

          $handler_settings = SubmissionHelper::getHandlerConfiguration($submission);

          $token_options['clear'] = TRUE;
          $message = $handler_settings['settings']['payment_done_message']['value'] ?? '';
          $message = $this->tokenManager->replaceNoRenderContext($message, $submission, [], $token_options);

          $this->messenger()->addMessage(Markup::create($message));
        }
      }
    }

    return $this->redirect('<front>');
  }

  /**
   * Validates webhooks from authorize net.
   *
   * @param Request $request
   *
   * @return Response
   */
  public function validateWebhook(Request $request) {
    $content = $request->getContent();
    $data = Json::decode($content);

    if (isset($data['payload']) && !empty($data['payload'])) {
      if (isset($data['payload']['merchantReferenceId'])) {
        $sid = SubmissionHelper::getSubmissionByReference($data['payload']['merchantReferenceId']);
        if ($sid) {
          SubmissionHelper::updatePaymentStatus($sid, PaymentStatus::COMPLETE);
          return new Response('TRUE', 200);
        }
      }
    }

    return new Response('', 204);
  }

}
