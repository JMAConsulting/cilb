<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

class CRM_Mjwshared_Webhook {

  /**
   * Get the path of the webhook depending on the UF (eg Drupal, Joomla, Wordpress)
   *
   * @param string $paymentProcessorID
   *
   * @return string
   */
  public static function getWebhookPath($paymentProcessorID) {
    return CRM_Utils_System::url('civicrm/payment/ipn/' . $paymentProcessorID, NULL, TRUE, NULL, FALSE, TRUE);
  }

}
