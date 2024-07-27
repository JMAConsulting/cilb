<?php

namespace Drupal\webform_authorizenet\Utility;

use Drupal\Component\Utility\Html;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Defines submission helper functions.
 */
class SubmissionHelper {

  /**
   * {@inheritdoc}
   */
  public static function getConfigurationWithSubmissionContext(array $configuration, WebformSubmissionInterface $webform_submission) {
    $webform_token = \Drupal::service('webform.token_manager');

    $token_options = [
      'clear' => TRUE,
    ];

    $data = [];
    foreach ($configuration as $configuration_key => $configuration_value) {
      $is_token_value = !is_array($configuration_value) && preg_match('/^\[.*\]$/', $configuration_value) === 1;
      if ($is_token_value) {
        $token_options['clear'] = TRUE;

        // Get replace token values.
        $configuration_value = $webform_token->replaceNoRenderContext($configuration_value, $webform_submission, [], $token_options);

        $configuration_value = Html::decodeEntities($configuration_value);
      }

      if ($configuration_key === 'number_of_items' && (int) $configuration_value < 1) {
        $configuration_value = 1;
      }

      $data[$configuration_key] = $configuration_value;
    }

    return $data;
  }

  /**
   * Provides webform submission id by transaction reference id.
   *
   * @param $rid
   *   The transaction reference id.
   *
   * @return integer|null
   */
  public static function getSubmissionByReference($rid) {
    $query = \Drupal::database()->select('webform_submission_data', 'wsd');
    $query->addField('wsd', 'sid');
    $query->condition('wsd.name', 'anet_transaction_reference');
    $query->condition('wsd.value', $rid);
    $id = $query->execute()->fetchField();
    return $id;
  }

  /**
   * Updates transaction_reference field for webform_submission.
   *
   * @param $sid
   *   The webform submission id.
   * @param $value
   *   The transaction_reference value.
   */
  public static function updateTransactionReference($sid, $value) {
    $query = \Drupal::database()->update('webform_submission_data');
    $query->fields([
      'value' => $value,
    ]);
    $query->condition('sid', $sid);
    $query->condition('name', 'anet_transaction_reference');
    $query->execute();
  }

  /**
   * Updates payment status field for webform_submission.
   *
   * @param $sid
   *   The webform submission id.
   * @param $value
   *   The payment status value.
   */
  public static function updatePaymentStatus($sid, $value) {
    $query = \Drupal::database()->update('webform_submission_data');
    $query->fields([
      'value' => $value,
    ]);
    $query->condition('sid', $sid);
    $query->condition('name', 'anet_payment_status');
    $query->execute();
  }

  /**
   * Gets the authorizenet handler configuration from given submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $submission
   *   The webform submission.
   *
   * @return array
   *   The handler configuration.
   */
  public static function getHandlerConfiguration(WebformSubmissionInterface $submission): array {
    return $submission->getWebform()->getHandler('webform_authorize_net_handler')->getConfiguration();
  }

}
