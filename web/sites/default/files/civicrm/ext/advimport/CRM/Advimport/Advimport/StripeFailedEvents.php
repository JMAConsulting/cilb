<?php

use CRM_Advimport_ExtensionUtil as E;

class CRM_Advimport_Advimport_StripeFailedEvents {

  /**
   * Returns a human-readable name for this helper.
   */
  public function getHelperLabel() {
    return E::ts("Stripe Failed Events");
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
      'event_id',
      'created',
      'payment_processor_id',

      // This is just to help with debugging
      'type',
      'data',
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

    // Stripe only retains 30 days of events, so no need to worry about
    // fetching too much data
    $subscriptions = \Stripe\Event::all([
      'limit' => 100,
      'delivery_success' => false,
    ]);

    foreach ($subscriptions->autoPagingIterator() as $val) {
      $t = [];

      $data[] = [
        'event_id' => $val->id,
        'created' => date('Y-m-d H:i:s', $val->created),
        'payment_processor_id' => $pp['id'],
        'type' => $val->type,
        'data' => json_encode($val),
      ];
    }

    return [$headers, $data];
  }

  /**
   * This is not used because we do not create contacts.
   */
  function getGroupOrTagLabel($params) {
    return 'Stripe Event Import ' . date('Y-m-d H:m');
  }

  function getMapping() {
    return [];
  }

  /**
   * Import an item gotten from the queue.
   */
  function processItem($params) {
    // Copied from mjwshared
    $UFWebhookPath = CRM_Utils_System::url('civicrm/payment/ipn/' . $params['payment_processor_id'], NULL, TRUE, NULL, FALSE, TRUE);

    $client = new \GuzzleHttp\Client();

    $data = json_decode($params['data']);

    // Nb: this can throw an exception, which will be caught by advimport
    $post = $client->post($UFWebhookPath, [
      \GuzzleHttp\RequestOptions::JSON => $data,
    ]);

    $code = $post->getStatusCode();

    if ($code != 200) {
      throw new Exception("Failed: http code: $code");
    }
  }

}
