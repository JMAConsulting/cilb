<?php

use Civi\Api4\Contact;
use CRM_Ses_ExtensionUtil as E;

/**
 * Simple Email Service webhook page class.
 *
 * Listens and processes bounce events from Amazon SNS
 */
class CRM_Ses_Page_Webhook extends CRM_Core_Page {

  /**
   * Verp Separator.
   *
   * @var string
   */
  protected $verp_separator;

  /**
   * CRM_Core_BAO_MailSettings::defaultLocalpart()
   *
   * @var string
   */
  protected $localpart;

  /**
   * The SES Notification object.
   *
   * @var object
   */
  protected $snsEvent;

  /**
   * The SES Message object.
   *
   * @var object
   */
  protected $snsEventMessage;

  /**
   * CiviCRM Bounce types.
   *
   * @var array
   */
  protected $civi_bounce_types = [];

  /**
   * GuzzleHttp Client.
   *
   * @var object
   */
  protected $client;

  /**
   * See https://docs.aws.amazon.com/ses/latest/DeveloperGuide/notification-contents.html#bounce-types
   */
  public function getBounceTypeId($bounceType, $bounceSubType) {
    $sesBounceTypes['Undetermined']['Undetermined'] = 'Invalid';
    $sesBounceTypes['Permanent']['General'] = 'Invalid';
    $sesBounceTypes['Permanent']['NoEmail'] = 'Invalid';
    $sesBounceTypes['Permanent']['Suppressed'] = 'Invalid';
    $sesBounceTypes['Permanent']['OnAccountSuppressionList'] = 'Invalid';
    $sesBounceTypes['Transient']['General'] = 'Relay';
    $sesBounceTypes['Transient']['MailboxFull'] = 'Quota';
    $sesBounceTypes['Transient']['MessageTooLarge'] = 'Relay';
    $sesBounceTypes['Transient']['ContentRejected'] = 'Spam';
    $sesBounceTypes['Transient']['AttachmentRejected'] = 'Spam';
    $bounceTypeName = $sesBounceTypes[$bounceType][$bounceSubType];
    return array_search($bounceTypeName, $this->civi_bounce_types);
  }

  /**
   * Constructor.
   */
  public function __construct() {
    $this->client = new GuzzleHttp\Client();

    $this->verp_separator = Civi::settings()->get('verpSeparator');
    $this->localpart = CRM_Core_BAO_MailSettings::defaultLocalpart();
    $this->civi_bounce_types = $this->get_civi_bounce_types();
    // get json input
    $this->snsEvent = json_decode(file_get_contents('php://input'));
    // message object - only decode when the envelope is a well-formed object carrying a string
    // Message. This endpoint is public, so a malformed body (e.g. {"Message":{}}) must not fatal
    // here before run() / verify_signature() get the chance to reject it.
    $this->snsEventMessage = (is_object($this->snsEvent) && isset($this->snsEvent->Message) && is_string($this->snsEvent->Message))
      ? json_decode($this->snsEvent->Message)
      : NULL;

    parent::__construct();
  }

  /**
   * @return void
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function run() {
    // If page was loaded incorrectly (eg. via a browser) snsEvent will not be a decoded object.
    // Exit gracefully. If we can't verify the SNS signature then we add a log message and exit.
    if (!is_object($this->snsEvent) || !$this->verify_signature()) {
      CRM_Utils_System::civiExit();
    }

    switch ($this->snsEvent->Type) {
      case 'SubscriptionConfirmation':
        // Confirm the SNS subscription and exit
        $this->confirm_subscription();
        CRM_Utils_System::civiExit();
        break;

      case 'Notification':
        break;

      default:
        CRM_Utils_System::civiExit();
    }

    // Every notification we can act on carries a `mail` object, but SES also
    // publishes notifications on the same topic that are not events at all:
    // pointing an identity's Bounce/Complaint feedback at a topic makes it
    // publish a "Successfully validated SNS topic" message. Ignore anything with
    // no `mail` rather than letting getVerpItemsFromSource() below dereference
    // the missing property, which raises a TypeError on PHP 8 - so the endpoint
    // answers 5xx and SNS retries a notification that can never succeed.
    if (!is_object($this->snsEventMessage)
      || !isset($this->snsEventMessage->mail)
      || !is_object($this->snsEventMessage->mail)) {
      // Identify the message without logging its body: this is also the branch a
      // future change to the SES payload shape would land in, and it would
      // otherwise be silent.
      \Civi::log()->info('ses: ignoring SNS notification with no mail payload.'
        . ' MessageId=' . ($this->snsEvent->MessageId ?? '(none)')
        . ' TopicArn=' . ($this->snsEvent->TopicArn ?? '(none)')
        . ' notificationType=' . ($this->snsEventMessage->notificationType ?? '(none)')
        . ' eventType=' . ($this->snsEventMessage->eventType ?? '(none)'));
      CRM_Utils_System::civiExit();
    }

    [$job_id, $event_queue_id, $hash] = $this->getVerpItemsFromSource();
    if ((empty($job_id) || empty($event_queue_id) || empty($hash)
      || !CRM_Mailing_Event_BAO_Queue::verify($job_id, $event_queue_id, $hash)) && $this->snsEventMessage->notificationType != 'Complaint') {
      \Civi::log()->error("ses: Unable to identify mailing - transactional? {$this->snsEventMessage->mail->source}. job_id={$job_id},event_queue_id={$event_queue_id},hash={$hash}" . print_r($this->snsEvent, TRUE));
      CRM_Utils_System::civiExit();
    }
    $bounce_params = [
      'job_id' => $job_id,
      'event_queue_id' => $event_queue_id,
      'hash' => $hash,
    ];

    switch ($this->snsEventMessage->notificationType) {
      case 'Bounce':
        $bounce_params = $this->set_bounce_type_params($bounce_params);
        if (!$this->checkIfBouncedEmailMatchesQueueEmail($event_queue_id, $bounce_params['emailAddresses'])) {
          CRM_Utils_System::civiExit();
        }
        if (empty($bounce_params['bounce_type_id'])) {
          // We couldn't classify bounce type - let CiviCRM try!
          $bounce_params['body'] = "Bounce Description: {$this->snsEventMessage->bounce->bounceType} {$this->snsEventMessage->bounce->bounceSubType}";
          civicrm_api3('Mailing', 'event_bounce', $bounce_params);
        }
        else {
          // We've classified the bounce type, record in CiviCRM
          CRM_Mailing_Event_BAO_Bounce::recordBounce($bounce_params);
        }
        break;

      case 'Complaint':
        $bounce_params = $this->map_complaint_types($bounce_params);
        // If we weren't able to map to a known event queue id let us still try and find the email(s) in the database and set the contact(s) on hold at least.
        if (empty($bounce_params['event_queue_id'])) {
          foreach ($bounce_params['emailAddresses'] as $emailAddress) {
            $contact_id = CRM_Core_DAO::singleValueQuery("SELECT contact_id FROM civicrm_email WHERE email = %1 AND contact_id IS NOT NULL LIMIT 1", [1 => [$emailAddress, 'String']]);
            if (!empty($contact_id)) {
              Contact::update(FALSE)
                ->addValue('is_opt_out', TRUE)
                ->addWhere('id', '=', $contact_id)
                ->execute();
              \Civi::log()->info('ses: Set is_opt_out for contactID: ' . $contact_id);
            }
          }
          // Always exit here, whether or not a contact was found: falling through with an
          // empty $event_queue_id would pass NULL into checkIfBouncedEmailMatchesQueueEmail()'s
          // typed `int $queueID` parameter below and throw a TypeError.
          CRM_Utils_System::civiExit();
        }
        if (!$this->checkIfBouncedEmailMatchesQueueEmail($event_queue_id, $bounce_params['emailAddresses'])) {
          CRM_Utils_System::civiExit();
        }
        // Opt out the contact and create entries for spam bounces (which only puts the email on hold).
        // This is because the contact likely reported the email as spam as a way to unsubscribe.
        // So opting out only the one email address instead of the contact risks getting any emails sent to their
        // secondary addresses flagged as spam as well, which can hurt our spam score.
        CRM_Mailing_Event_BAO_Bounce::recordBounce($bounce_params);
        $sql = "SELECT cc.id FROM civicrm_contact cc INNER JOIN civicrm_mailing_event_queue cmeq ON cmeq.contact_id = cc.id WHERE cmeq.id = %1";
        $sql_params = [1 => [$bounce_params['event_queue_id'], 'Integer']];
        $contact_id = CRM_Core_DAO::singleValueQuery($sql, $sql_params);

        if (!empty($contact_id)) {
          Contact::update(FALSE)
            ->addValue('is_opt_out', TRUE)
            ->addWhere('id', '=', $contact_id)
            ->execute();
          \Civi::log()->info('ses: Set is_opt_out for contactID: ' . $contact_id);
        }
        break;
    }
    CRM_Utils_System::civiExit();
  }

  /**
   * Currently the MailingEventQueue records a single email address but there may be multiple
   *  in the case of To, Cc, Bcc etc.
   * This function checks that the one referred in MailingEventQueue is actually bouncing
   *  and only records a bounce if it is.
   * It logs a warning for any other email addresses as they won't get recorded by CiviCRM currently.
   *
   * @param int $queueID
   * @param array $emailAddresses
   *
   * @return bool
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  protected function checkIfBouncedEmailMatchesQueueEmail(int $queueID, array $emailAddresses): bool {
    $mailingEventQueue = \Civi\Api4\MailingEventQueue::get(FALSE)
      ->addSelect('email.email')
      ->addJoin('Email AS email', 'LEFT')
      ->addWhere('id', '=', $queueID)
      ->execute()
      ->first();

    $matches = FALSE;
    foreach ($emailAddresses as $email) {
      if ($mailingEventQueue['email.email'] === $email) {
        $matches = TRUE;
      }
      else {
        \Civi::log()->warning("Email: {$email} bounced but does not match queue {$queueID} email: {$mailingEventQueue['email.email']}");
      }
    }
    return $matches;
  }

  /**
   * Get verp items from a source address in format eg. b.13.6.1d49c3d4f888d58a@example.org
   *
   * @return array The verp items [ $job_id, $queue_id, $hash ]
   */
  protected function getVerpItemsFromSource(): array {
    if (property_exists($this->snsEventMessage->mail, 'commonHeaders')
      && property_exists($this->snsEventMessage->mail->commonHeaders, 'returnPath')) {
      $sourceAddress = $this->snsEventMessage->mail->commonHeaders->returnPath;
    }

    if (empty($sourceAddress)) {
      \Civi::log()->warning('SES: Could not find returnPath! SNS event: ' . print_r($this->snsEvent, TRUE));
      return [];
    }

    // The source address doesn't look like it has a verp in it so lets try and see if we have a X-CiviMail-Bounce Header.
    if (!str_contains($sourceAddress, $this->verp_separator) && property_exists($this->snsEventMessage->mail, 'headers')) {
      foreach ($this->snsEventMessage->mail->headers as $headers) {
        if ($headers->name === 'X-CiviMail-Bounce') {
          $sourceAddress = $headers->value;
        }
      }
    }

    // Strip off the first/localpart
    $verpString = substr($sourceAddress, strlen($this->localpart) + 2);
    // Now strip off the domain
    $verpString = substr($verpString, 0, strpos($verpString, '@'));
    $verpItems = explode($this->verp_separator, $verpString);
    if (count($verpItems) > 1) {
      return $verpItems;
    }

    return [];
  }

  /**
   * Set bounce type params.
   *
   * @param array $bounce_params The params array
   *
   * @return array The params array
   */
  protected function set_bounce_type_params($bounce_params) {
    $bounce_params['bounce_type_id'] = $this->getBounceTypeId($this->snsEventMessage->bounce->bounceType, $this->snsEventMessage->bounce->bounceSubType);

    $reasonParts = [];
    $reasonParts[] = $this->snsEventMessage->bounce->bounceType;
    $reasonParts[] = $this->snsEventMessage->bounce->bounceSubType;
    foreach ($this->snsEventMessage->bounce->bouncedRecipients as $recipient) {
      $recipientReason = '';
      if (!empty($recipient->emailAddress)) {
        $recipientReason .= "email:{$recipient->emailAddress};";
        // See: https://lab.civicrm.org/extensions/ses/-/issues/6
        // SNS docs don't show that emailAddress might be formatted with displayname but real-world results do!
        // We are only interested in the actual email address so extract that
        $bouncedEmails[] = $this->verify_email_address($recipient->emailAddress);
      }
      if (!empty($recipient->action)) {
        $recipientReason .= "action:{$recipient->action};";
      }
      if (!empty($recipient->status)) {
        $recipientReason .= "status:{$recipient->status};";
      }
      if (!empty($recipient->diagnosticCode)) {
        $recipientReason .= "status:{$recipient->diagnosticCode};";
      }
      if (!empty($recipientReason)) {
        $reasonParts[] = "[$recipientReason]";
      }
    }
    $bounce_params['bounce_reason'] = "Bounce via SES: " . implode(" ", $reasonParts);
    $bounce_params['emailAddresses'] = $bouncedEmails ?? [];

    return $bounce_params;
  }

  /**
   * Parses a string and returns the first email address found
   *
   * The local-part class must keep `+`, or a sub-addressed recipient
   * (`user+tag@example.org`) matches only from after the last `+` and this
   * returns `tag@example.org` - an address the caller then either fails to
   * match against the queue row, or opts out the wrong contact by.
   *
   * @param $email_string
   * @return string
   */
  private function verify_email_address($email_string) {
    $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
    $email = '';

    if (preg_match($pattern, $email_string, $matches)) {
      $email = $matches[0];
    }
    return $email;
  }

  /**
   * Map Amazon complaint types to Civi bounce types.
   *
   * @param array $params The params array
   *
   * @return array The params array
   */
  protected function map_complaint_types($params) {
    $params['bounce_type_id'] = array_search('Spam', $this->civi_bounce_types);

    foreach ($this->snsEventMessage->complaint->complainedRecipients as $recipient) {
      // See: https://lab.civicrm.org/extensions/ses/-/issues/8
      // SNS docs don't show that emailAddress might be formatted with displayname but real-world results do!
      // We are only interested in the actual email address so extract that
      $complaintEmails[] = $this->verify_email_address($recipient->emailAddress);
    }

    $reasonParts = [];
    if (!empty($this->snsEventMessage->complaint->userAgent)) {
      $reasonParts[] = $this->snsEventMessage->complaint->userAgent;
    }
    if (!empty($this->snsEventMessage->complaint->complaintFeedbackType)) {
      $reasonParts[] = $this->snsEventMessage->complaint->complaintFeedbackType;
    }
    if (!empty($this->snsEventMessage->complaint->complaintSubType)) {
      $reasonParts[] = $this->snsEventMessage->complaint->complaintSubType;
    }
    if (!empty($complaintEmails)) {
      $reasonParts[] = '[' . implode(";", $complaintEmails) . ']';
    }
    if ($reasonParts) {
      $params['bounce_reason'] = "Complaint via SES: " . implode(" ", $reasonParts);
    }
    else {
      $params['bounce_reason'] = "Complaint via SES (no further details)";
    }

    $params['emailAddresses'] = $complaintEmails ?? [];
    return $params;
  }

  /**
   * Confirm SNS subscription to topic.
   *
   * @see https://docs.aws.amazon.com/sns/latest/dg/sns-message-and-json-formats.html#http-subscription-confirmation-json
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function confirm_subscription() {
    // To confirm the subscription we must "visit" the provided SubscribeURL
    $this->client->request('POST', $this->snsEvent->SubscribeURL);
    \Civi::log()->info('ses: SNS subscription confirmed');
  }

  /**
   * Verify SNS Message signature.
   *
   * @see https://docs.aws.amazon.com/sns/latest/dg/SendMessageToHttp.verify.signature.html
   * @return bool true if successful
   */
  protected function verify_signature() {
    // Optional TopicArn allow-list. TopicArn is part of the SNS-signed payload, so on its own it is
    // not proof of origin - it only matters alongside the signature check below. But it must be
    // enforced HERE (before the Type switch in run()) so it also gates SubscriptionConfirmation -
    // otherwise the webhook would auto-confirm a subscription to any topic.
    $expectedTopicArn = Civi::settings()->get('ses_sns_topic_arn');
    if (!empty($expectedTopicArn)) {
      $topicArn = (isset($this->snsEvent->TopicArn) && is_string($this->snsEvent->TopicArn)) ? $this->snsEvent->TopicArn : '';
      if ($topicArn !== $expectedTopicArn) {
        \Civi::log()->error('ses: SNS message rejected - TopicArn not allow-listed: ' . $topicArn);
        return FALSE;
      }
    }

    // keys needed for signature
    $keys_to_sign = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
    // for SubscriptionConfirmation the keys are slightly different
    if ($this->snsEvent->Type == 'SubscriptionConfirmation') {
      $keys_to_sign = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
    }

    // build message to sign. Only string fields are signed (a real SNS message has all of these as
    // strings), so the is_string() check never rejects a genuine message but stops a non-string field
    // in a hostile POST from fataling on interpolation - it's just omitted, so the signature fails.
    $message = '';
    foreach ($keys_to_sign as $key) {
      if (isset($this->snsEvent->$key) && is_string($this->snsEvent->$key)) {
        $message .= "{$key}\n{$this->snsEvent->$key}\n";
      }
    }

    if (!isset($this->snsEvent->Signature) || !is_string($this->snsEvent->Signature)
      || !isset($this->snsEvent->SigningCertURL) || !is_string($this->snsEvent->SigningCertURL)) {
      \Civi::log()->error('ses: SNS signature verification failed! Missing Signature or SigningCertURL - check you have "Raw Message Delivery: Disabled" on the subscription');
      return FALSE;
    }

    // Validate the SigningCertURL host BEFORE fetching it. Amazon SNS always serves its signing
    // certificate from https://sns.<region>.amazonaws.com/... (incl. the GovCloud/China
    // amazonaws.com.cn forms). Without this check the URL is attacker-controlled, so a forged message
    // could be made to verify against a certificate the attacker hosts anywhere.
    $certUrl = $this->snsEvent->SigningCertURL;
    // parse_url() returns FALSE on a malformed URL and omits absent components; ?? '' normalises both
    // so an empty scheme/host fails the checks below (fail-closed).
    $certParts = parse_url($certUrl) ?: [];
    if (($certParts['scheme'] ?? '') !== 'https'
      || !preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/', $certParts['host'] ?? '')) {
      \Civi::log()->error('ses: SNS signature verification failed! Untrusted SigningCertURL: ' . $certUrl);
      return FALSE;
    }

    // decode SNS signature
    $sns_signature = base64_decode($this->snsEvent->Signature);

    // get certificate from SigningCertURL and extract public key
    $public_key = openssl_get_publickey($this->fetchSigningCertPem($this->snsEvent->SigningCertURL));

    // verify signature
    $signed = openssl_verify($message, $sns_signature, $public_key, OPENSSL_ALGO_SHA1);

    if ($signed && $signed != -1) {
      return TRUE;
    }

    \Civi::log()->error('ses: SNS signature verification failed!');
    return FALSE;
  }

  /**
   * Fetch the PEM-encoded certificate at a (by this point, already
   * host-validated) SigningCertURL. Isolated behind a method so tests can
   * substitute a local test certificate instead of hitting the network.
   *
   * @param string $url
   *
   * @return string
   */
  protected function fetchSigningCertPem(string $url): string {
    return file_get_contents($url);
  }

  /**
   * Get CiviCRM bounce types.
   *
   * @return array
   */
  protected function get_civi_bounce_types() {
    if (!empty($this->civi_bounce_types)) {
      return $this->civi_bounce_types;
    }

    $query = 'SELECT id,name FROM civicrm_mailing_bounce_type';
    $dao = CRM_Core_DAO::executeQuery($query);

    $civi_bounce_types = [];
    while ($dao->fetch()) {
      $civi_bounce_types[$dao->id] = $dao->name;
    }

    return $civi_bounce_types;
  }

}
