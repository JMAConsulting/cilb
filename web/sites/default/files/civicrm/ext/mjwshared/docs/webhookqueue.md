# Webhook Queue

Payment processors can generate *a lot* of webhooks; when some *event* occurs such as a payment is confirmed, information about this event is packaged up in a special way and sent through an HTTP request to a special CiviCRM *webhook endpoint URL*.

Multiple webhooks can arrive simultaneously, they may be re-sent in the case of network failure for example, may be delayed by various environmental factors. This means that events may be received out of chronological order, and one event may already be obsolete by the time it gets processed.

We must be able to accept this data quickly and efficiently, but processing the events may take time. A sudden flurry of events can degrade the performance of the site or cause time-outs and processing failures. This extension provides a framework for queueing webhooks for scheduled background execution using the `PaymentprocessorWebhook` entity and Scheduled Job.

To use this functionality you must add support to your Payment Processor.

Depending on the 3rd party sending the webhook, the data might contain authentication keys/be encoded, cryptographically signed and may describe a single event or may bundle a lot of events in one go.

On receiving the data, we need to do *as little processing as possible*, to ensure efficiency. Typically this might be: authenticating the request (many webhooks contain a pre-shared secret to check), validating the data, and possibly extracting multiple events into multiple queue items. Perhaps the 3rd party provides some library code function that must be used to unpack this. Most 3rd parties keep a record of what they have sent, and how the receiving server responded, so that they can re-send later in the case of failure. As we're not (necessarily) processing the events at this point, we can reply successfully as long as we were able to unpack the event(s) and put them on the queue.

## PaymentprocessorWebhook entity

The table `civicrm_paymentprocessor_webhook` records each event from an incoming webhook along with information required to process it, a processing status field and a result message.

The fields are:

- `id` CiviCRM-internal integer, as standard.

- `payment_processor_id` this is a foreign key to a configured payment processor.

- `event_id` a string field to store an event's unique identifier, as provided by the 3rd party.

- `trigger` *optional* a string machine-name description of the event, again processor dependent. This might be a field you can extract directly from the webhook data, or it might be something you need to fabricate from various data. Example: Stripe uses a *trigger* field with values like `payment_succeeded`. GoCardless sends an entity and action in separate fields (`payments` and `confirmed`), and implementers can choose how to store these, e.g. GoCardless stores this as the string `payments.confirmed`.

- `created_date` a timestamp recording when the queue item was created.

- `processed_date` a timestamp recording when the queue item was processed.

- `status` a string, described below.

- `identifier` an *optional* string to group possible multiple events together. Stripe uses this since many events may come in about a particular contribution and these then need processing in a particular order.

- `message` an *optional* text string recording the result of the processing. Error messages are useful here, though more detail may be found in other logs, depending on the implementation.

- `data` TEXT. Stores the (rest of the) data received. You may not need to use this, event ID and trigger might be enough (e.g. Stripe), but sometimes the data sent includes more information that is required or useful in processing, e.g. a GoCardless event might include subscription IDs and dates that are useful. The field defined as TEXT, so JSON is a sensible format for encoding the data.

### Processing status

* `new`: The webhook has been received but not yet processed.
* `error`: The webhook has been processed but there was an error.
* `success`: The webhook has been processed successfully.
* `processing`: The webhook is currently being processed by the API3 `Job.process_paymentprocessor_webhooks` (scheduled job).

## Querying the webhook table

Use the API4 `PaymentprocessorWebhook` entity.

## Implementing the queue in your payment processor

Your payment processor will have a subclass of `CRM_Core_Payment` with all its specific code in. This is referred to as the "payment class" throughout this section of documentation.

### First, edit your payment class' `handlePaymentNotification()` method. This should

1. examine, unpack, verify, authenticate etc. the incoming webhook request. (We assume that you already have this code written).

2. Split the webhook data into *events* that you need to process. If the processor sends events you don't use, you might want to skip these at this stage (no point queuing something that doesn't require action!).

3. Assuming there are events you wish to process *now or by schedule*, create queue items for each of them. Example pseudo code below.

4. If you want to process the event right away, you can pass the data to `$this->processWebhookEvent($queueItemArray)`.

5. Create a suitable http response. Typically a blank response with a suitable `http_response_code()`.

 ```php
 <?php
public function handlePaymentNotification() {

  try {
    /** @var array of whatever the 3rd party events look like (must be JSON serializable) */
    $processorEvents = checkAndParseIncomingWebhookDataIntoEvents(file_get_contents('php://input'));

    /** @var array of data for PaymentprocessorWebhook entity */
    $storedEvents = [];
    $eventsToProcessRightNow = [];
    foreach ($processorEvents as $processorEvent) {
      if (weCompletelyIgnoreThisType($processorEvent['type'])) {
        continue;
      }
      $storedEvent = [
        'event_id' => $processorEvent['id'],
        'trigger'  => $processorEvent['eventType'],
        'data'     => json_encode($processorEvent),
        // 'identifier' => $this->getIdentifierValueForEvent($processorEvent),
      ];
      if (weWantToProcessThisEventNow($processorEvent)) {
        $storedEvent['status'] = 'processing';
        $eventsToProcessRightNow[$processorEvent['id']] = NULL;
      }
    }

    // Store the events. (They will receive status 'new')
    $storedEvents = \Civi\Api4\PaymentprocessorWebhook::save(FALSE)
      ->setRecords($storedEvents)
      ->setDefaults(['payment_processor_id' => $this->getID(), 'created_date' => 'now'])
      ->execute()
      ->indexBy('event_id')
      ->getArrayCopy();

    if ($eventsToProcessRightNow) {
      // Map external event IDs to our new queue IDs.
      foreach ($eventsToProcessRightNow as $eventID => $_) {
        $eventsToProcessRightNow[$eventID] = $storedEvents[$eventID]['id'];
      }
      // Reload the queue items (to populate the rest of the fields)
      $queueItems = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
        ->addWhere('id', 'IN', $eventsToProcessRightNow)
        ->execute();
      foreach ($queueItems as $webhookEvent) {
        $this->processWebhookEvent($webhookEvent);
      }
    }
  }
  catch (Exception $e) {
    // Aah, shucks. Log it and let the 3rd party know it should
    // retry later by returning 400, for example.
    http_response_code(400);
  }

  // Assuming you don't need to provide any http body to the 3rd party...
  exit;
}
```

### Then, create a `processWebhookEvent(array $webhookEvent)` method in your payment class.

This receives the row from `civicrm_paymentprocessor_webhook` (from `PaymentprocessorWebhook`) as an array. It should:

1. attempt to process the data however it needs to.
2. catch all exceptions
3. update the webhook event entity recording the status success/error and any message
4. return TRUE for success, FALSE for error

```php
<?php
public function processWebhookEvent(array $webhookEvent) :bool {
  try {
    $webhookEvent['processed_date'] = 'now';
    // Pseudo code (doesn't have to be a separate method)
    $result = $this->doTheDo(json_decode($webhookEvent['data']));
    $webhookEvent['status'] = 'success';
    $webhookEvent['message'] = 'have a nice day';
    return TRUE;
  }
  catch (Exception $e) {
    $webhookEvent['status'] = 'error';
    $webhookEvent['message'] = $e->getMessage();
    $result = FALSE;
  }
  // Update the stored event.
  Civi\Api4\PaymentprocessorWebhook::save(FALSE)
    ->setRecords([$webhookEvent])->execute();

  return $result;
}
```

# Legacy notes.


Currently it is only implemented for Stripe and `civicrm_api3_job_process_paymentprocessor_webhooks` function
would need to be modified to call the appropriate API method for that processor instead of `Stripe.Ipn`. The
intention is to support `Ipn` API for any supported processor.

This is the paymentprocessor function that receives the webhook:
```php
  public function handlePaymentNotification() {
    $rawData = file_get_contents("php://input");
    $ipnClass = new CRM_Core_Payment_StripeIPN($rawData);
    if ($ipnClass->onReceiveWebhook()) {
      http_response_code(200);
    }
  }
```

This is the paymentprocessor function that is used to manually process a webhook and is called from API3 `Stripe.Ipn`:
```
  public static function processPaymentNotification($paymentProcessorID, $rawData, $verifyRequest = TRUE, $emailReceipt = NULL) {
    $_GET['processor_id'] = $paymentProcessorID;
    $ipnClass = new CRM_Core_Payment_StripeIPN($rawData, $verifyRequest);
    $ipnClass->setExceptionMode(FALSE);
    if (isset($emailReceipt)) {
      $ipnClass->setSendEmailReceipt($emailReceipt);
    }
    return $ipnClass->processWebhook();
  }
```

In your IPN code instead of using a `main()` method create two functions:

* `onReceiveWebhook()`: Triggered whenever a webhook is received. Use this to record the webhook.
* `processWebhook()`: This is the method that actually processes the webhook and may be called immediately or via the scheduled job.

```php
  /**
   * Get a unique identifier string based on webhook data.
   *
   * @return string
   */
  private function getWebhookUniqueIdentifier() {
    return "{$this->charge_id}:{$this->invoice_id}:{$this->subscription_id}";
  }

  /**
   * When CiviCRM receives a Stripe webhook call this method (via handlePaymentNotification()).
   * This checks the webhook and either queues or triggers processing (depending on existing webhooks in queue)
   *
   * @return bool
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   * @throws \Stripe\Exception\UnknownApiErrorException
   */
  public function onReceiveWebhook() {
    if (!in_array($this->eventType, CRM_Stripe_Webhook::getDefaultEnabledEvents())) {
      // We don't handle this event, return 200 OK so Stripe does not retry.
      return TRUE;
    }

    $uniqueIdentifier = $this->getWebhookUniqueIdentifier();

    // Get all received webhooks with matching identifier which have not been processed
    // This returns all webhooks that match the uniqueIdentifier above and have not been processed.
    // For example this would match both invoice.finalized and invoice.payment_succeeded events which must be
    // processed sequentially and not simultaneously.
    $paymentProcessorWebhooks = \Civi\Api4\PaymentprocessorWebhook::get(FALSE)
      ->addWhere('payment_processor_id', '=', $this->_paymentProcessor->getID())
      ->addWhere('identifier', '=', $uniqueIdentifier)
      ->addWhere('processed_date', 'IS NULL')
      ->execute();
    $processWebhook = FALSE;
    if (empty($paymentProcessorWebhooks->rowCount)) {
      // We have not received this webhook before. Record and process it.
      $processWebhook = TRUE;
    }
    else {
      // We have one or more webhooks with matching identifier
      /** @var \CRM_Mjwshared_BAO_PaymentprocessorWebhook $paymentProcessorWebhook */
      foreach ($paymentProcessorWebhooks as $paymentProcessorWebhook) {
        // Does the eventType match our webhook?
        if ($paymentProcessorWebhook->trigger === $this->eventType) {
          // Yes, We have already recorded this webhook and it is awaiting processing.
          // Exit
          return TRUE;
        }
      }
      // We have recorded another webhook with matching identifier but different eventType.
      // There is already a recorded webhook with matching identifier that has not yet been processed.
      // So we will record this webhook but will not process now (it will be processed later by the scheduled job).
    }

    \Civi\Api4\PaymentprocessorWebhook::create(FALSE)
      ->addValue('payment_processor_id', $this->_paymentProcessor->getID())
      ->addValue('trigger', $this->eventType)
      ->addValue('identifier', $uniqueIdentifier)
      ->addValue('event_id', $this->event_id)
      ->execute();

    if (!$processWebhook) {
      return TRUE;
    }

    return $this->processWebhook();
  }

  /**
   * Process the given webhook
   *
   * @return bool
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function processWebhook() {
    try {
      $success = $this->processEventType();
    }
    catch (Exception $e) {
      $success = FALSE;
      \Civi::log()->error('StripeIPN: processWebhook failed. ' . $e->getMessage());
    }

    $uniqueIdentifier = $this->getWebhookUniqueIdentifier();

    // Record that we have processed this webhook (success or error)
    // If for some reason we ended up with multiple webhooks with the same identifier and same eventType this would
    // update all of them as "processed". That is ok because we don't need to process the "same" webhook multiple
    // times. Even if they have different event IDs but the same identifier/eventType.
    \Civi\Api4\PaymentprocessorWebhook::update(FALSE)
      ->addWhere('identifier', '=', $uniqueIdentifier)
      ->addWhere('trigger', '=', $this->eventType)
      ->addValue('status', $success ? 'success' : 'error')
      ->addValue('processed_date', 'now')
      ->execute();

    return $success;
  }

```
