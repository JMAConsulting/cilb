# Amazon SES / SNS Configuration for CiviCRM

Send email using the SES API. Bounce processing is handled via SNS and a webhook that must be
authenticated against the CiviCRM system. This guide will use "example" as a placeholder; replace with your own domain / abbreviation as appropriate.

## Request Production Access

See https://docs.aws.amazon.com/ses/latest/dg/request-production-access.html on getting access to send email.

## SES Configuration

1. Log in to the AWS console
2. Open SES (using top search bar is easiest)
3. Click **Verified identities**.
4. Create identity:
   - **Identity Type**: Domain
   - **Domain name**: Enter your domain
   - **DKIM**: Easy DKIM and RSA_2048_BIT
   - **DKIM signatures**: Enabled
5. Wait for DKIM verification (can take a few hours).

### DNS Configuration

Add the **three CNAME records** provided by AWS SES.

Add `amazonses.com` to your SPF record. Example:

`v=spf1 include:amazonses.com ~all`

Make sure you have a suitable DMARC policy setup for your domain.

## SNS Configuration

Once the system is running on the live domain, now setup bounce and complaint handling via SNS.

### Create SNS Topic

1. Open **Amazon SNS**.
2. Create topic:
   - **Type**: Standard
   - **Name**: domain name (e.g. `example_org`)
   - **Encryption**: Disabled
   - **Access policy**: Default
   - **Delivery retry policy**: Default

### Create SNS Subscription

1. Select the topic you just created.
2. Create subscription:
   - **Protocol**: HTTPS
   - **Endpoint**: https://example.org/civicrm/ses/webhook
   - **Raw message delivery**: Disabled

### Connect SNS to SES

Open SES > Identities and find your domain to configure notifications:
   - **Email feedback forwarding**: Enabled
   - **Bounce feedback**: SNS topic created earlier
   - **Complaint feedback**: SNS topic created earlier
   - **Include original email headers**: Enabled

## CiviCRM SES Extension

Install and configure the CiviCRM SES extension (https://lab.civicrm.org/extensions/ses).

1. Login to AWS console and confirm it's the region you want e.g. "us-east-1".
2. Create new IAM user (e.g. `example_civi`)
3. Find / Create group `SES-SendRawEmail` with `AmazonSesSendingAccess` permission added.
4. Create access key and copy key/secret to Administer > CiviMail > Amazon SES.
5. Copy the **SNS Topic ARN** of the topic created above (shown on the topic's page in the SNS console, e.g. `arn:aws:sns:us-east-1:123456789012:example_org`) into the **SNS Topic ARN** field at Administer > CiviMail > Amazon SES. This pins the webhook to your topic so it rejects validly-signed notifications published from any other SNS topic. Recommended for anyone using bounce/complaint processing; leave blank to accept notifications from any topic.
6. Go to Administer -> System Settings -> Outbound Email, and set it "mail()". Also disable the option to let users to send emails using their own email address, unless you have validated every possible staff email.
7. Go to Administer -> Communications -> FROM Email Addresses, and only enable email addresses that have been validated by SES.
8. Make sure that the "Domain" and "Email" in "Mail Accounts" for "Bounce Processing" account matches one of the verified identities in AWS SES.
9. You can use the SES placeholder for account protocol for your Bounce Processing account. (The SES protocol doesn't actually do anything here - the ideal would be to not require the bounce account at all when using SES but that requires some changes to the internal verp/domain processing to not pull the domain from the bounce account.)

Note: If you do not see the permission -> add permission -> create an inline policy for `AmazonSesSendingAccess` group as follows:

```
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "ses:SendRawEmail",
            "Resource": "*"
        }
    ]
}
```

## Suppression List Removal (Optional)

The extension can optionally remove an email address from your SES account-level suppression list whenever that address is taken off "on hold" in CiviCRM. This is useful because CiviCRM's own bounce processing already covers the reverse direction: a hard bounce or complaint against an address on the suppression list gets classified as `Invalid` and CiviCRM sets `on_hold` automatically.

1. Go to Administer > CiviMail > SES settings.
2. Tick **"Remove from SES suppression list on un-hold"** (unticked/off by default).
3. Grant the configured IAM user the additional `ses:DeleteSuppressedDestination` permission alongside the ses:SendRawEmail permission set up above: 

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "ses:SendRawEmail",
            "Resource": "*"
        },
        {
            "Effect": "Allow",
            "Action": "ses:DeleteSuppressedDestination",
            "Resource": "*"
        }
    ]
}
```

Without this IAM permission, removal attempts will fail; failures are logged (`Civi::log('ses')`) but do not block the CiviCRM save.

**Warning:** Check other installed extensions for any that implement hook_civicrm_alterMailParams. Review what the extension does and do additional testing. Depending on whether the extension hooks runs before or after this one could result in different behaviour - for example headers being changed that will cause bounce processing to fail.

## CMS Email Integration

### Wordpress

Install https://lab.civicrm.org/extensions/wp-civicrm-mailer

No configuration is required. This will automatically route all email through CiviCRM and no other mailer plugin should be enabled.

### Drupal

- Drupal 8+: https://lab.civicrm.org/extensions/civicrmmailer-d8
- Drupal 7: https://lab.civicrm.org/extensions/civicrmmailer-d7

## Additional Information

- Amazon's [guide and documentation](https://docs.aws.amazon.com/ses/latest/DeveloperGuide/configure-sns-notifications.html) on how to setup SNS notifications for SES.

### Webhook URL

- WordPress: https://example.org/civicrm/ses/webhook (or https://example.org/?page=CiviCRM&q=civicrm%2fses%2fwebhook if "clean URLs" are not enabled)
- Drupal: https://example.org/civicrm/ses/webhook

The webhook verifies that the _Notification_ or _SubscriptionConfirmation_ it's been originated and sent by the SNS service (as per SNS [docs](https://docs.aws.amazon.com/sns/latest/dg/SendMessageToHttp.verify.signature.html)).
