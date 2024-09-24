# Hooks

## webhookEventNotMatched

This allows you to implement custom handling for unrecognised/unknown webhook events.

Example implementation: https://github.com/mjwconsult/civicrm-stripewebhookrules

For example if you use the same Stripe account to take payments through multiple systems
(eg. online shop, CiviCRM) you will receive webhooks for payments to both systems.
But only the payments that were created using CiviCRM will be matched.

By implementing this hook you can choose to do something with those payments from external
systems - eg. add them into CiviCRM. Once they are in CiviCRM they will be handled like any
other payment in future and subscriptions will continue to be updated automatically in CiviCRM.

```php
/**
 * @param string $type The type of webhook - eg. 'stripeipn'
 * @param Object $object The object (eg. CRM_Core_Payment_StripeIPN)
 * @param string $code "Code" to identify what was not matched (eg. customer_not_found)
 * @param array $result Results returned by hook processing. Depends on the type/code. Eg. for stripe.contribution_not_found return $result['contribution'] = "contribution array from API"
 *
 * @return mixed
 */
function myextension_civicrm_webhookEventNotMatched(string $type, $object, string $code = '', array &$result) {
  if ($type !== 'stripe') {
    return;
  }
  if (!($object instanceof CRM_Core_Payment_StripeIPN) && !($object instanceof \Civi\Stripe\Webhook\Events)) {
    return;
  }
  switch ($code) {
    case 'customer_not_found':
      createStripeCustomerInCiviCRM($object);
      break;

    case 'contribution_not_found':
      // If you have a rule to find/create a matching contribution put it in the result array:
      $result['contribution'] = Contribution::get(...);
      break;
  }
}
```
