## Migration from legacy Authorize.Net (SilentPost / CiviCRM Core processor)

CiviCRM core ships with an old payment processor for Authorize.Net which uses an unsupported, insecure API
and [SilentPost](https://support.authorize.net/s/article/Silent-Post-URL) for IPN notifications.

To migrate to the new processor provided by this extension the simplest way is to change
the payment processor type in CiviCRM:
![list of payment processors in CiviCRM](images/civicrm_authnetprocessors.png)

Select one of Credit Card or eCheck.Net instead of the legacy one.

You should not need to update your *API Login ID* or *Transaction Key* but you will need to
obtain a new *Signature Key* from your Authorize.Net dashboard - see [setup](setup.md) for more details.

#### Can I enable SilentPost and Webhooks simultaneously?

Yes! If you have both enabled they will both receive notifications about the same transaction in their own format.

This extension will simply ignore the "Silent Post" notifications but any existing system listening to the Silent Post URL
will still be able to receive and process the notifications.
