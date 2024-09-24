# Payment Shared library

This library is used by all payment processors by MJW and other extensions.

It provides multiple functions such as APIs, refund UI, shared code and a compatibility layer to support multiple versions of CiviCRM without requiring explicit support in the payment processor.

The main "goals" of this extension are:
- Provide an abstraction layer between CiviCRM core and payment extensions so
that we don't have to write and maintain the same code in every extension.
- Provide a compatibility layer between CiviCRM core and payment extensions so
that we don't force sites to upgrade CiviCRM core versions just to keep their Payment Processor working.
(Generally we target a minimum CiviCRM core version based on the last security release).
- Provide a "staging" environment for proving new APIs / interfaces that should eventually become
a standard part of CiviCRM core.
- Provide a rapid way of fixing bugs in Payment Processing without forcing a CiviCRM core update
(eg. we can issue a new release of "Payment Shared" containing a workaround for bugs in specific versions
of CiviCRM core).

## Setup

#### Job.process_paymentprocessor_webhooks

This job processes new webhook events in the `civicrm_paymentprocessor_webhook` table.

* Run: Always
* Domain-specific: YES. This job MUST be run on every domain you have setup if using multisite/multidomain.

## Support and Maintenance

This extension is supported and maintained by:

[![MJW Consulting](images/mjwconsulting.jpg)](https://www.mjwconsult.co.uk)

We offer paid [support and development](https://mjw.pt/support) as well as a [troubleshooting/investigation service](https://mjw.pt/investigation).
