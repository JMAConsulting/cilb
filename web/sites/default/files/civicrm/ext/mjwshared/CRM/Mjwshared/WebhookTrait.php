<?php

/**
 * This is kept for now to prevent crashes when updating mjwshared to 1.5+ before updating extensions
 *   that depend on this and use the WebhookTrait
 *
 * @deprecated
 */
trait CRM_Mjwshared_WebhookTrait {

  /**
   * @param $paymentProcessorId
   *
   * @return string
   * @deprecated
   */
  public static function getWebhookPath($paymentProcessorId) {
    return '';
  }

}

