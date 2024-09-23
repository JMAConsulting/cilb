<?php

/**
 * @param array $params
 *   Array of parameters determined by getfields.
 */
function _civicrm_api3_mjwpayment_notificationretry_spec(&$params) {
  $params = [
    'system_log_id' => [
      'type' => CRM_Utils_Type::T_INT,
      'title' => 'System Log ID',
    ],
  ];
}

/**
 * Process incoming payment notification
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_mjwpayment_notificationretry($params) {
  if (!empty($params['system_log_id'])) {
    // let's replace params with this rather than allow altering
    $logEntry = civicrm_api3('system_log', 'getsingle', ['id' => $params['system_log_id'], 'return' => ['context', 'message']]);
  }
  $dataRaw = $logEntry['context'];

  if (substr($logEntry['message'], 0, 34) === 'payment_notification processor_id=') {
    $paymentProcessorId = substr($logEntry['message'], 34);
    $paymentProcessorType = civicrm_api3('PaymentProcessor', 'getsingle', ['id' => $paymentProcessorId]);
  }
  else {
    throw new API_Exception('Unsupported payment processor');
  }

  $processorClassName = "CRM_Core_{$paymentProcessorType['class_name']}";
  if (!method_exists($processorClassName, 'processPaymentNotification')) {
    throw new API_Exception('Unsupported payment processor');
  }

  $result = FALSE;
  $errorMessage = 'try checking the logs for errors';
  try {
    $result = $processorClassName::processPaymentNotification($paymentProcessorId, $dataRaw, FALSE);
  }
  catch (Exception $e) {
    $errorMessage = $e->getMessage();
  }
  if ($result) {
    return civicrm_api3_create_success(1, $params, 'Mjwpayment', 'Notificationretry');
  }
  return civicrm_api3_create_error("Failed to process notification - {$errorMessage}");
}
