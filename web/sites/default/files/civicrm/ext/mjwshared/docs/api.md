# API4

## ContributionRecur.updateAmountOnRecurMJW

Accepts `amount` parameter.

Accepts standard `where` clause to select recurring contributions.
You can update multiple recurring contributions at the same time
if multiple are returned by the `where` clause.

This updates a recurring contribution, associated contribution and lineitems with a new amount.
Should be called eg. after calling changeSubscriptionAmount on a paymentprocessor to
reflect the changes in CiviCRM.

Logic:
- Find or create a template contribution for the recur.
- Update the template contribution with the new amount.
- CiviCRM core automatically updates LineItems and Recur amounts.

Notes:
Will fail if (template) contribution has more than one LineItem.

## Membership.LinkToRecurMJW

Accepts `membershipID` parameter.

This links a membership to a recurring contribution and takes care of updating
related entities (contribution, template contribution, lineitem) so that the
membership will automatically update/renew.

## Membership.UnlinkFromRecurMJW

This unlinks a membership from a recurring contribution and takes care of updating
related entities (contribution, template contribution, lineitem) so that history is
preserved but future payments will not be linked to or renew the membership.

## PriceFieldValue.GetDefaultPriceFieldValueForContributionMJW

No parameters. This returns an array containing the defaul contribution price_field_value_id:

`$result = ['price_field_id' = X, 'price_field_value_id' = Y, 'label' = price_field_value.label]`

## PriceFieldValue.GetDefaultPriceFieldValueForMembershipMJW

One parameter: `membershipID`.

You must specify a membership ID from which the membership type and default price_field_value_id for 
that type will be returned as an array:

`$result = ['price_field_id' = X, 'price_field_value_id' = Y, 'label' = price_field_value.label]`

## PaymentMJW.create

Use like API3 Payment.create. This should not yet be used in production code as it is still subject to change
and does not yet have test coverage.

## PaymentMJW.refund

Calls PaymentProcessor.refund and records a refund in CiviCRM

# API3 (Deprecated)

This extension comes with several APIs to help you troubleshoot problems. These can be run via /civicrm/api or via drush if you are using Drupal (drush cvapi Mjwpayment.XXX).

### Mjwpayment.notificationretry

For supported payment processors (ie. those that use Mjwshared) you can retry IPN notifications using this API.

#### Parameters
* `system_log_id`: The stored notification log entry to retry. Find using SystemLog.get API.
