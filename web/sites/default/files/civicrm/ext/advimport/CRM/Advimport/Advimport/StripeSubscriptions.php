<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_StripeSubscriptions {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("Stripe Subscriptions");
  }

  function mapfieldMethod() {
    return 'skip';
  }

  /**
   * File upload not required.
   */
  function validateUploadForm(&$form) {
    return true;
  }

  function getDataFromFile($file = null) {
    $data = [];
    $headers = [
      'subscription_id',
      'created',
      'payment_processor_id',
      'plan',
      'amount',
      'interval',
      'currency',

      // Client information
      // @todo Not certain if first/last name is always there?
      'email',
      'first_name',
      'last_name',
      'city',
      'country',
      'street_address',
      'postal_code',
      'state_province',
      'phone',
    ];

    $result = civicrm_api3('PaymentProcessor', 'get', [
      'class_name' => 'Payment_Stripe',
      'is_test' => 0,
      'sequential' => 1,
    ]);

    if (empty($result['values'][0])) {
      throw new Exception('Stripe Payment Processor not found.');
    }

    $pp = $result['values'][0];

    $sk = CRM_Core_Payment_Stripe::getSecretKey($pp);
    \Stripe\Stripe::setAppInfo('CiviCRM', CRM_Utils_System::version(), CRM_Utils_System::baseURL());
    \Stripe\Stripe::setApiKey($sk);
    \Stripe\Stripe::setApiVersion(CRM_Stripe_Check::API_VERSION);

    $subscriptions = \Stripe\Subscription::all(['limit' => 100]);

    foreach ($subscriptions->autoPagingIterator() as $val) {
      $t = [];

      $t['subscription_id'] = $val->id;
      $t['created'] = date('Y-m-d H:i:s', $val->created);
      $t['payment_processor_id'] = $pp['id'];

      // This is only for convenience
      $t['plan'] = $val->plan->id;
      $t['amount'] = $val->plan->amount;
      $t['interval'] = $val->plan->interval;
      $t['currency'] = $val->plan->currency;

      // Fetch customer data
      $customer = \Stripe\Customer::retrieve([
        'id' => $val->customer,
      ]);

      $t['email'] = $customer->email;

      if (isset($customer->metadata->first_name)) {
        $t['first_name'] = $customer->metadata->first_name;
        $t['last_name'] = $customer->metadata->last_name;
      }

      if (isset($customer->shipping->address)) {
        $t['city'] = $customer->shipping->address->city;
        $t['street_address'] = $customer->shipping->address->line1;
        $t['postal_code'] = $customer->shipping->address->postal_code;
        $t['country'] = $customer->shipping->address->country;
        $t['state_province'] = $customer->shipping->address->state;
      }

      if (isset($customer->shipping->phone)) {
        $t['phone'] = $customer->shipping->phone;
      }

      $data[] = $t;
    }

    return [$headers, $data];
  }

  /**
   * Customize the name of the group or tag that will be greated for the
   * imported contacts.
   */
  function getGroupOrTagLabel($params) {
    return 'Stripe Subscriber Import ' . date('Y-m-d H:m');
  }

  function getMapping() {
    return [];
  }

  /**
   * Import an item gotten from the queue.
   */
  function processItem($params) {
    $fields = [];
    // Only deduping by email for now
    $contact_id = null;

    $result = civicrm_api3('Contact', 'get', [
      'email' => $params['email'],
      'return' => ['email', 'first_name', 'last_name'],
      'sequential' => 1,
    ])['values'];

    if (!empty($result[0])) {
      $contact_id = $result[0]['id'];

      // If the contact has a name, make sure the firstname matches
      // Otherwire we create a duplicate and let later deduping handle it.
      if (!empty($result[0]['first_name']) && !empty($params['first_name'])) {
        $a = mb_strtolower($result[0]['first_name']);
        $b = mb_strtolower($params['first_name']);

        if ($a != $b) {
          $contact_id = null;
        }
      }
      elseif (empty($result[0]['first_name']) && empty($result[0]['last_name']) && !empty($params['first_name'])) {
        // Assume it's OK to update the contact name, since it was empty in CiviCRM
        civicrm_api3('Contact', 'create', [
          'contact_id' => $contact_id,
          'first_name' => $params['first_name'],
          'last_name' => $params['last_name'],
        ]);
      }
    }

    if (!$contact_id) {
      $result = civicrm_api3('Contact', 'create', [
        'contact_type' => 'Individual',
        'first_name' => $params['first_name'],
        'last_name' => $params['last_name'],
        'email' => $params['email'],
      ]);

      $contact_id = $result['id'];
    }

    CRM_Advimport_Utils::setEntityTableAndId($params, 'civicrm_contact', $contact_id);

    $location_type_id = CRM_Advimport_Utils::getDefaultLocationType();

    if (!empty($params['phone'])) {
      CRM_Advimport_Utils::updateContactRelatedEntity('Phone', [
        'location_type_id' => $location_type_id,
        'contact_id' => $contact_id,
        'phone' => $params['phone'],
      ]);
    }

    if (!empty($params['country'])) {
      $address = [
        'contact_id' => $contact_id,
        'location_type_id' => $location_type_id,
      ];

      $address['country_id'] = CRM_Advimport_Utils::getCountryID($params['country'], [
        'column' => 'name',
      ]);

      $fields = ['street_address', 'city', 'postal_code'];

      foreach ($fields as $f) {
        if (!empty($params[$f])) {
          $address[$f] = $params[$f];
        }
      }

      $x = CRM_Advimport_Utils::getStateProvinceID($params['state'], [
        'column' => 'abbreviation',
        'country_id' => $address['country_id'],
      ]);

      if ($x) {
        $address['state_province_id'] = $x;
      }

      CRM_Advimport_Utils::updateContactRelatedEntity('Address', $address);
    }

    // Import the Stripe Subscription
    $result = civicrm_api3('StripeSubscription', 'import', [
      'subscription_id' => $params['subscription_id'],
      'contact_id' => $contact_id,
      'payment_processor_id' => $params['payment_processor_id'],
    ]);

    if (!empty($fields['tag_contact']) && !empty($params['source'])) {
      CRM_Advimport_Utils::addContactToGroupByName($contact_id, $params['source']);
    }
  }

}
