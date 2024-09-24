# CiviCRM Authorize.Net Payment processor

CiviCRM Extension that integrates with the Authorize.Net payment provider using Credit Card and echeck (ACH/EFT).

#### Authorize.net CreditCard/eCheck

![authnet cc echeck preview](images/authnet_preview.png)

#### Authorize.net Accept.js ACH/EFT:
![authnet acceptjs ach](images/authnet_acceptjs_ach.png)

#### Authorize.net Accept.js CreditCard:
![authnet acceptjs creditcard](images/authnet_acceptjs_creditcard.png)

## Features

* Provides a payment processor for the Authorize.net Accept.js API.
* Provides a payment processor eCheck.Net/Credit Card based on Authorize.Net API (AIM Method).
* Supports Recurring Contributions using Authorize.Net Automated Recurring Billing (ARB)
* Supports Webhooks: https://developer.authorize.net/api/reference/features/webhooks.html
* Supports Refunds.

## Installation

**The [mjwshared](https://lab.civicrm.org/extensions/mjwshared) extension is required and MUST be installed.**

## Setup

1. Add a New Payment Processor of type `Authorize.Net (eCheck.Net)` or `Authorize.Net (Credit Card)` in the menu via *Administer->System Settings->Payment Processors*.

## Webhooks

Webhooks are configured automatically when a payment processor is created.
A System Check message will identify problems and help set them up if required.

## Development

* Webhooks based on stymiee/authnetjson library - http://www.johnconde.net/blog/handling-authorize-net-webhooks-with-php/

## Support and Maintenance
This extension is supported and maintained with the help and support of the CiviCRM community by:

[![MJW Consulting](images/mjwconsulting.jpg)](https://www.mjwconsult.co.uk)

We offer paid [support and development](https://mjw.pt/support) as well as a [troubleshooting/investigation service](https://mjw.pt/investigation).
