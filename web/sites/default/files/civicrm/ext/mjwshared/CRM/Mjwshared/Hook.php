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

/**
 * This class implements hooks for Mjwshared
 */
class CRM_Mjwshared_Hook {

  /**
   * This hook allows modifying recurring contribution parameters
   *
   * @param string $type The type of webhook - eg. 'stripe'
   * @param object $object The object (eg. CRM_Core_Payment_StripeIPN)
   * @param string $code "Code" to identify what was not matched (eg. customer_not_found)
   * @param array $result Results returned by hook processing. Depends on the type/code. Eg. for stripe.contribution_not_found return $result['contribution'] = "contribution array from API"
   *
   * @return mixed
   */
  public static function webhookEventNotMatched(string $type, $object, string $code = '', array &$result = []) {
    // Wrap in a try/catch to guard against coding errors in extensions.
    try {
      return CRM_Utils_Hook::singleton()
        ->invoke([
          'type',
          'object',
          'code',
          'result'
        ], $type, $object, $code, $result, CRM_Utils_Hook::$_nullObject, CRM_Utils_Hook::$_nullObject,
          'civicrm_webhook_eventNotMatched'
        );
    }
    catch (Exception $e) {
      \Civi::log()->error("webhookEventNotMatched triggered exception. Type: {$type}; Code: {$code}; Message: " . $e->getMessage());
      return FALSE;
    }
  }

}
