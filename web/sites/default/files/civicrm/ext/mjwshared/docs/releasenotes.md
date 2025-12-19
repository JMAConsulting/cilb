## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

* **[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 1.5.2 (2025-12-15)

* [!58](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/58) Fix crash on Drupal 7.

## Release 1.5.1 (2025-12-11)

*  Add WebhookTrait back in as deprecated to avoid crashes if mjwshared is upgraded to 1.5+ before related extensions.

## Release 1.5.0 (2025-12-04)

**Requires CiviCRM 6.9.0 because it depends on https://github.com/civicrm/civicrm-core/pull/33694**

**This release contains breaking changes and will require updating extensions that depend on MJWShared.**

* Allow refunding manual payments. Add 'Record Refund' link to contributions. Use 'Refund contribution' permission.
* Use AbstractBatchAction instead of AbstractUpdateAction for UpdateRecurOnRecurMJW.
* Update deprecated php type casts.
* Move system checks to a different class.
* Remove WebhookTrait (use CRM_Mjwshared_Webhook::getWebhookPath() directly instead).
* Drop support for minifier extension.

## Release 1.4.4 (2025-10-15)

* Deprecate most of our custom API3 functions.
* Remove use of mjwpayment.get_contribution from mjwshared (use API4 Payment.Get instead).

## Release 1.4.3 (2025-08-27)

* Additional fix for webhook check if payment_processor_id is NULL.

## Release 1.4.2 (2025-08-26)

* [!53](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/53) Modify webhook check to work with Only Full Group By SQL Mode.
* [!52](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/52) Fix detection of recurring for memberships on contribution pages.

## Release 1.4.1 (2025-07-07)

* Regenerate code (civix upgrade). Min version CiviCRM 6.0.

## Release 1.4.0 (2025-04-04)

* Add SearchKit-based payment processor webhooks display (and remove old one)
* Add support for getting totalAmount from contribution pages in invoice mode - requires [civicrm-core#32574](https://github.com/civicrm/civicrm-core/pull/32574).

## Release 1.3.4 (2025-02-26)

* Make sure we always activate customdata from managed entities
* Add civicrm-ext type to composer.json

## Release 1.3.3 (2025-01-06)

* Change ManagedEntity to update policy always (prevents conflicts with other extensions and fixes issues with them disappearing in certain circumstances).
* Fix regression on required checkbox fields.
* Fix JS crash if name is undefined.

## Release 1.3.2 (2024-10-13)

* Add check for webhooks stuck in processing status - if this is triggered something probably needs fixing manually.
* Replace deprecated exception.
* Switch to entity framework v2.
* Switch to mgd files to define cg_extend_objects - used to allow custom fields on "FinancialTrxn" entity. Added in a way that doesn't conflict with other extensions.

## Release 1.3.1 (2024-08-26)

* [!46](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/46) Ensure autorenew works with memberships.

## Release 1.3 (2024-07-17)

* Add new API4 functions:
  * ContributionRecur.updateAmountOnRecurMJW
  * Membership.LinkToRecurMJW
  * Membership.UnlinkFromRecurMJW
  * PriceFieldValue.GetDefaultPriceFieldValueForContributionMJW
  * PriceFieldValue.GetDefaultPriceFieldValueForMembershipMJW
  * PaymentMJW.create

See [API docs](api.md) for more information.

* Add search identifier and raw data options to Payment Processor Webhooks UI.


## Release 1.2.22 (2024-03-09)

* Fix message display on PaymentProcessorWebhook UI when message is NULL.
* Add generic Logger class `\Civi\MJW\Logger` - use this to standardise/simplify log messages.
* Update getDefaultCurrencyForForm() to use standard form functions where available.
* Update some functions to use API4 internally.
* Replace deprecated `CiviCRM_API3_Exception`.
* More robust `error_url` handling.
* Fix currency shown on refund form.

## Release 1.2.21 (2024-02-09)

* Switch Payment processor webhooks menu entry to managed entity.
* Add a settings page (Administer->CiviContribute->Payment Shared Settings).
* [!44](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/44) Ensure email confirm is sent for events when using stripe checkout (Fixes extensions/stripe#456).
* Add feature to disable (hide) core 'Record Refund' link on edit contribution.
* Add handling for billingCountry on update subscription.

## Release 1.2.20 (2023-12-16)

* Fix CustomField params for Mjwpayment.create_payment.
* Fix setting of recur ID for changeSubscriptionAmount.

## Release 1.2.19 (2023-12-15)

* Add CustomFields to API3 MJWPayment.create spec - makes the custom fields "discoverable" via the API3 explorer.
* Update beginChangeSubscriptionAmount() function - for Payment Processors that allow you to update the subscription amount it should now be slightly more reliable.

## Release 1.2.18 (2023-11-21)

* [!42](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/42) avoid extra redirect; correctly show auth.net errors on failed transaction.

## Release 1.2.17 (2023-10-16)

**You MUST update to this version if running CiviCRM 5.66 otherwise webhooks will stop working**

* Fix message field should not be required (CiviCRM core enforces required fields for API4 from 5.66).
* Cleanup custom fields.

## Release 1.2.16 (2023-10-16)
**Do not use this release**

It was released with a broken upgrader.

## Release 1.2.15 (2023-09-10)

* Fix [civicrm-core/#4553](https://lab.civicrm.org/dev/core/-/issues/4553) getBillingEmail() function causes fatal error when email is not passed in correctly.

## Release 1.2.14 (2023-08-14)

* [!41](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/41) The extension fails to install or upgrade if the option value for cg extends already present in the database (fix compatibility with extensions that use custom fields on financial transactions).

## Release 1.2.13 (2023-06-26)

* smartyv2-1.0.1 does not work on 5.59 because of https://github.com/civicrm/civicrm-core/commit/7168793c03ef57c06bbfe45f5ff873ebb3657806
so we set minimum version to 5.58 which triggers civix to ship as an extension mixin.

## Release 1.2.12 (2023-06-25)

* Add Payment_details custom field group and allow custom fields to be saved via API3 `Mjwpayment.create`.
* Pass through custom params in `updateContributionCompleted()`.

* Convert some internals to API4.
* Refactor API call to fix payment processor name issue.
* Use getter for `_paymentProcessor`.

## Release 1.2.11 (2023-01-30)

* Remove our version of CRM_Core_Payment::getAmount() as it was merged into core in 5.37.
* Fix undefined variable basePage on frontend formbuilder pages.

## Release 1.2.10 (2022-11-22)

* Make sure calculateTaxAmount always returns a valid float.
* Stop recommending installation of minifier extension (it can cause problems with some angularjs scripts).
* Check both frontend/backend URL for AJAX requests.
* Add indexes to civicrm_paymentprocessor_webhook - see [Stripe#395](https://lab.civicrm.org/extensions/stripe/-/issues/395).
* Fix translation [!31](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/31).

## Release 1.2.9 (2022-10-14)

* Add psr0 classloader to info.xml.
* Don't update (set update to never) Paymentprocessorwebhooks managed job (stops it re-enabling automatically).
* Upgrade civix.
* Fix [#18](https://lab.civicrm.org/extensions/mjwshared/-/issues/18) Don't add refund link if no payment processor.

## Release 1.2.8 (2022-08-19)

* Multiple participants: Handle 100% discount. Fix [Stripe#372](https://lab.civicrm.org/extensions/stripe/-/issues/372) etc. when additional participant amount is more than first participant.
* Convert getTokenParameter() to use propertyBag.
* Fix [!35](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/35) style issues with Greenwich; also nicer formatting of raw data.

## Release 1.2.7 (2022-07-20)

* Minimum supported version of Stripe extension is now 6.7.
* Add `\Civi\Paymentshared\WebhookEventIgnoredException` for use by payment processors.
* Fix deprecated API4 join.

## Release 1.2.6 (2022-06-14)

* Add support for percentagepricesetfield/extrafee extensions (was previously supported but broke in 1.2.3).
* Support partial refunds.
* Fix [#8](https://lab.civicrm.org/extensions/mjwshared/-/issues/8) Support cancelling memberships when issuing refunds.

## Release 1.2.5 (2022-05-19)

* Separate `trxn_id` and `order_reference` params and prefer `trxn_id` in return values from `doPayment()`. This means that both are now available for use.
* Refunds: Add a lock around recording refund payment in `MJWIPNTrait::updateContributionRefund()`. This means we should not record a duplicate refund if both UI and IPN are processed at the same time.
* Update return params from `doRefund()`.
* [!33](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/33) Add processor filter to webhooks list page.

## Release 1.2.4

* Fix [!30](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/30) Pledges are also recurring.
* Set `civicrm_contribution_recur.processor_id` if still using `trxn_id`.

## Release 1.2.3

* [!29](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/29) Throw exception on error (when using handleError() function).
* Fix [#10](https://lab.civicrm.org/extensions/mjwshared/-/issues/10) Replace .prop() with .attr() when selecting BillingFormID.
* Improve `CRM.payment.getIsRecur()` so it returns early if recur is not supported.
* `Mjwpayment.get_contribution` API supports contribution_id - update spec.
* Replace core `calculateTotalFee()` javascript function with our own. This makes us independent of core changes - eg. see https://github.com/civicrm/civicrm-core/pull/22759.
* Calculate the total amount for multiple event participants when calling `CRM.payment.getTotalAmount()`.

## Release 1.2.2

* Add result parameter to webhookEventNotMatched and update example.
* Add getter/setter for contributionRecurID in IPN trait.
* Add link to example hook implementation for webhookEventNotMatched (https://github.com/mjwconsult/civicrm-stripewebhookrules).
* Enable js debugging for drupal webform.

## Release 1.2.1

* Fix display of 'Error' status on webhook UI.
* More helpful error messages when IPN processing fails.
* Job.process_paymentprocessor_webhooks needs to be domain-specific (setup to run on each domain).
* Add event_id and queue_limit to Job.process_paymentprocessor_webhooks.
* Add deleted count to Job.process_paymentprocessor_webhooks.
* Only delete old webhook entries for our domain (when multiple domains configured).
* Add system check to make sure that scheduled jobs are setup on all domains.

## Release 1.2

**Thanks to [ArtfulRobot](https://artfulrobot.uk) this release improves the webhook queueing system
and adds a user interface to view/manage webhooks.**

* Implement `processWebhookEvent()` - This receives and processes the row from `civicrm_paymentprocessor_webhook` (from `PaymentprocessorWebhook`).
* Update schema and add indexes to `civicrm_paymentprocessor_webhooks` table.
* Improve api3 `Job.ProcessPaymentprocessorWebhooks` return data and add time.
* Add angular app to view/manage webhooks.
* Fully remove support for CiviCRM older that 5.35.

## Release 1.1
**This release *should* be compatible with payment processors that require 1.0 or higher.
But make sure you test before upgrading.**

* Add multiple functions to CRM.payment (that were previously in civicrmStripe.js):
    * resetBillingFieldsRequiredForJQueryValidate
    * setBillingFieldsRequiredForJQueryValidate
    * addDrupalWebformActionElement
    * doStandardFormSubmit
    * validateReCaptcha
    * addSupportForCiviDiscount
    * displayError
    * swalFire
    * swalClose
    * triggerEvent
* Minor (backwards-compatible) fixes/changes to existing functions (eg. setting class variables directly instead of relying on return values).
* Refactor checks class, move checks from Stripe to mjwshared:
    * Check for Sweetalert extension.
    * Check for "Separate Membership Payment" is enabled.
* Support X.X-dev versioning for system checks - display a warning if dev version, version check no longer fails if eg. using 1.1-dev and minimum requirement is 1.1.
* Return a fixed set of params from `doPayment()` - see [dev/financial/issues#141](https://lab.civicrm.org/dev/financial/-/issues/141).
* Move cast to PropertyBag to beginDoPayment (reduce lines of code required in doPayment).
* Automatically handle deprecated `trxn_id` on `civicrm_contribution_recur` (copy from `processor_id` and add deprecated warnings).
* Add `beginUpdateSubscriptionBillingInfo()` and `beginChangeSubscriptionAmount()` methods - see [Payment Processor](paymentprocessor.md).
* Convert internal method `getContactID()` to require propertyBag.
* Define contributionRecur property on IPN class.
* Add new [hook `webhookEventNotMatched`](hooks.md).
* Add handling for multiple js payment processors and delayed crmBillingFormReloadComplete event trigger.
* Fix invalid currency on some event registration forms.
* Fix for non-default Wordpress basepage and AJAX reload of payment elements.

## Release 1.0.1

* Fix [!22](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/22) Handle deprecated API4 joins in PaymentProcessorwebhook API.

## Release 1.0

* Add PaymentprocessorWebhook entity, API and scheduled job that allows for queueing and scheduling of webhooks - see [Webhook Queue](webhookqueue.md)
* Fully remove support for CiviCRM older than 5.28.
* Add IPN getters/setters to provide object oriented initialisation.
* IPN data can be array, object or string.
* Clear cancel_date when setting a contribution back to pending.
* Support CiviCRM multi-domain (add default domain to API calls).
* Add `handleErrorThrowsException` option to MJWTrait (to help with testing).
* Total Amount is always required when completing contribution (`MJWIPNTrait::updateContributionCompleted()`).
* Set the 'payment_status' and add helper functions on doPayment() - see https://lab.civicrm.org/dev/financial/-/issues/141.
* Don't require total_amount for repeatContribution - it is set automatically via the template contribution of the recurring contribution.
* Fix [!19](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/19) Contributions held for fraud then approved don't send receipts - Send receipts on one-time payment notifications (if configured to do so via Contribution page).
* Enable [Refund UI](https://docs.civicrm.org/mjwshared/en/latest/refunds/) by default.
* "Javascript debugging" is now moved from Stripe to this library. If you have it enabled you will need to enable it again.

## Release 0.9.12

* Fix [#7](https://lab.civicrm.org/extensions/mjwshared/-/issues/7) Parse through thousands separators in calculateTaxAmount.
* Fix [!18](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/18) Incorrect financial transaction on repeatTransaction (Always pass payment_processor_id to Mjwshared.create_payment).

## Release 0.9.11

* Add `supportsRecur()` function to CRM.payment.
* Add `getPaymentProcessorSelectorValue()` function to CRM.payment.
* Fix [!15](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/15) Stripe loading on drupal 8 webforms.

## Release 0.9.10

* Add `getBillingEmail()` and `getBillingName()` functions to CRM.payment library.

## Release 0.9.9

* Trap and log exceptions triggered when calling repeatcontribution.
* Fix [Stripe!121](https://lab.civicrm.org/extensions/stripe/-/merge_requests/121) Drupal Webform: Recognize 0 installments as recurring.
* Add function processZeroAmountPayment to check/handle a zero amount payment.

## Release 0.9.8

**This affects new installs only. It should be completely safe to upgrade existing sites from 0.9.7 to 0.9.8**

* Fix [#6](https://lab.civicrm.org/extensions/mjwshared/-/issues/6) Install error on 0.9.7.
    - This was because the new settings file referenced a class from the Stripe extension.

## Release 0.9.7

* Add support for issuing refunds via the payment UI for payment processors that support refunds (eg. Stripe).
* Fix [Stripe#260](https://lab.civicrm.org/extensions/stripe/-/issues/260) Refund not communicated back to CiviCRM properly (CiviCRM < 5.32).

## Release 0.9.6

* Fix [Stripe#271](https://lab.civicrm.org/extensions/stripe/-/issues/271) Can't submit credit card memberships: Uncaught (in promise) TypeError: this.form is null

## Release 0.9.5

* Fix [#4](https://lab.civicrm.org/extensions/mjwshared/-/issues/4) Fatal error when is_email_receipt = null.

## Release 0.9.4

* Fix [#2](https://lab.civicrm.org/extensions/mjwshared/-/issues/2) Don't update receive_date when marking a contribution as failed.

## Release 0.9.3

* Add `getBillingSubmit()` to CRM.payment.

## Release 0.9.2

* Load CRM.payment library via coreResourceList so it is added everywhere CiviCRM is loaded (eg. drupal_webform etc)
* Fix stripe#238 two receipts sent for subscriptions
* Fix params for updateContributionFailed (id => contribution_id)
* Fix Mjwpayment.get_contribution
* Add getCurrency() function to CRM.payment
* Add 'Install now' to minifier/contributiontransactlegacy extensions now they are available for automated distribution

## Release 0.9.1

* Add workaround for [#17777](https://github.com/civicrm/civicrm-core/pull/17777) so receive_date is not updated on contribution (<5.29). Wrap workaround for order_reference in (<5.27) block
* Fix Failed->Completed for `updateContributionCompleted()` (you now need to pass in `contribution_status_id` as a parameter).
* Fix issues with params for Contribution.repeattransaction and IPNs (wrong params were being passed causing issues with completing contributions and duplicate payments).
* Check if we've already loaded CRM.payment library and don't reload if we have.
* Log errors if payment processor cannot be found for IPN.

## Release 0.9

**We are renaming this library to "Payment Shared". In some places you will see "Mjwshared" and in others "Payment Shared". They are the same thing!**

* Allow completing a contribution that has Failed status via `updateContributionCompleted()`.
* Add basic function for updating a contribution (eg. the `trxn_id`) without touching other things.
* Don't trigger exception if payment processor ID not found for IPN, use debug function because we don't have access to getPaymentProcessorLabel() function.

#### API (v3)

* Update `Mjwpayment.get_payment` spec.
* Refactor `Mjwpayment.get_contribution` so it accepts `order_reference` and `trxn_id` params and returns a single contribution with matching payments.
* Use `Mjwpayment.create_payment` instead of `Payment.create` API in `updateContributionRefund()` for compatibility with multiple versions of CiviCRM.
* Add `Mjwpayment.notificationretry` that allows retrying IPN notifications stored in the `civicrm_system_log` table.

#### CRM.payment library

* Load crm.payment.js before any payment processor scripts. Loaded in `page-header` unless client is webform-civicrm when we load in `billing-block`.
* Add getIsRecur() function.
* Allow the client to override/define their own CRM.payment.getTotalAmount() function (currently used by webform_civicrm - see https://github.com/colemanw/webform_civicrm/pull/331).

#### Shared PHP libraries (MjwTrait and MjwIPNTrait)

* **[BC]** Convert beginDoPayment and getRecurringContributionId now require a `\Civi\Payment\PropertyBag` object instead of an array as parameter.


## Release 0.8.1

* Fixes and improvements to system checks.
* Enhance getErrorUrl function and fixes for CiviCRM 5.27+

## Release 0.8
**This release contains breaking changes**

* Update `updateContributionCompleted`, `updateContributionFailed`, `updateContributionRefunded`, `repeatContribution` IPN functions so they now take `order_reference` and `trxn_id` parameters.

  *You need to update `contribution_trxn_id` -> `order_reference` and `payment_trxn_id` to `trxn_id`.*

* Switch to contribution.repeattransaction and payment.create API functions.
* Initial support for \Civi\Payment\PropertyBag. Add new CRM.payment library. Add WebhookTrait

## Release 0.7

* Implement buildAsset hook so that assets can be loaded via AssetBuilder without the [minifier](https://lab.civicrm.org/extensions/minifier) extension being available.
* Recommend minifier extension (and implement a dummy buildAsset hook so extensions using buildAsset for the minifier will still work without it).
* Recommend contributiontransactlegacy extension if drupal webform_civicrm is enabled.
* Implements setExceptionMode to allow skipping the exit on exception policy [!5](https://lab.civicrm.org/extensions/mjwshared/-/merge_requests/5).
* Add compat functions to work around issues with `\Civi::resources()->addVars()` - This improves compatibility for forms with multiple payment processors.
* Update Mjwpayment.get_payment API to support multiple parameters and options per https://github.com/civicrm/civicrm-core/pull/17071 (CiviCRM 5.26).

## Release 0.6

* Improve updateContributionRefund() function to handle new `order_reference` field and use `Payment.create` API.
* Simply calls in Contribution.getbalance to improve performance.
* Add check to warn if nfp worldpay extension is installed as it breaks things!
* Add currency symbol to Contribution.getbalance

## Release 0.5.1

* Fix getBillingEmail() to work in more circumstances and add tests

## Release 0.5

* Add Contribution.GetBalance API

## Release 0.4.6

* Fix missing return array on getTokenParameter.

## Release 0.4.5

* Remove setTokenParameter, modify getTokenParameter as we're now using pre_approval_parameters in Stripe 6.2

## Release 0.4.4

* Record a full refund correctly

## Release 0.4.3

* Improvements to get/setTokenParameter.
* Add js validation to event registration form.

## Release 0.4.2

* Fix params passed to repeatTransaction - this was causing some repeating contributions to fail.

## Release 0.4.1

* Fix 'is not boolean' error on IPNs. `getIsTestMode()` was returning TRUE/FALSE but the API requires 1/0.

## Release 0.4

* Fix issue with non-default currency on form when you can choose from more than one payment processor on the form.
* Add `getTokenParameter()`/`setTokenParameter()` functions to MJWTrait which should be used when setting parameters
via javascript (eg. Stripe `paymentIntentID`) which are required when the payment is actually processed (via `doPayment()`).

## Release 0.3

* Major refactor of MJWIPNTrait.
* Add function to update the transaction ID for a payment related to a contribution.

## Release 0.2

* Add function to get configured currency for contributionpage/event registration page.

## Release 0.1

* Initial release
