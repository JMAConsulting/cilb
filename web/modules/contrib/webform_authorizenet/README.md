This module enables a site administrator to allow payments through a Webform
submission through Authorize.Net. It provides a handler and integration to make
payments using Authorize.Net. Utilizes Accept Hosted, a mobile-optimized payment
form hosted by Authorize.Net.

After submitting the webform, the user will be redirected to a straightforward
checkout form to review payment information such as the number of items
and total amount. Upon confirmation, the user will proceed to Authorize.Net for
payment processing.

## Installation

1. Install the module using composer
2. Make sure you have the webform module enabled
3. Activate the module via admin/modules

## Usage

Once you have installed the module, an extra webform handler should be
available. This extra handler is labeled "Webform Authorize.Net Handler".

Before adding the handler, you have to ensure that the webform has the fields
with the following machine names: anet_payment_status,
anet_transaction_reference. These fields must be able to store string values
for transaction processing by the handler. Make sure these fields are not
changeable by users. For instance, you can use webform element "Text field"
with disabled Create and Update accesses and View access for admin only. These
fields should be added manually by administrator.

## Origin

The module is the successor of
- [authorizenetwebform (Drupal 7)](https://www.drupal.org/project/authorizenetwebform)
- [ivan-trokhanenko/authorizenetwebform](https://github.com/ivan-trokhanenko/authorizenetwebform)

## Author

- [Volodymyr Knyshuk](https://www.drupal.org/user/3536002)
- [Justin Phelan](https://www.drupal.org/user/54285)
