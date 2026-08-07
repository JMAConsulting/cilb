<?php

use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\Mailing;
use Civi\Api4\MailingEventQueue;
use Civi\Api4\MailingJob;
use Civi\Api4\MailSettings;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

require_once __DIR__ . '/WebhookTestable.php';

/**
 * Covers CRM_Ses_Page_Webhook::run() dispatch (Type/notificationType routing)
 * and the bounce/complaint mapping helpers it calls, against real Mailing/
 * MailingJob/MailingEventQueue/Contact/Email fixtures. Signature verification
 * itself is covered separately in WebhookVerifySignatureTest, so these
 * fixtures use CRM_Ses_Page_WebhookTestable::$forceVerifySignatureResult to
 * bypass it.
 *
 * @group headless
 */
class CRM_Ses_Page_WebhookDispatchTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  private string $domain = 'example.org';

  /**
   * Not empty string: CiviCRM coerces an empty-string localpart to NULL on
   * save, which would defeat the point of setting it explicitly here.
   *
   * @var string
   */
  private string $localpart = 'lp';

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    // Deterministic VERP construction/parsing, independent of whatever this
    // site happens to have configured.
    MailSettings::update(FALSE)
      ->addWhere('is_default', '=', TRUE)
      ->addValue('localpart', $this->localpart)
      ->addValue('domain', $this->domain)
      ->execute();
    // defaultDAO() caches the row in Civi::$statics per domain, so without
    // this the update above is invisible to defaultLocalpart()/defaultDomain().
    CRM_Core_BAO_MailSettings::clearCache();
    Civi::settings()->set('verpSeparator', '.');
  }

  /**
   * SubscriptionConfirmation confirms and exits, without reaching any of the
   * bounce/complaint dispatch logic below.
   */
  public function testSubscriptionConfirmationConfirmsAndExits(): void {
    $webhook = $this->buildWebhook(['Type' => 'SubscriptionConfirmation']);

    $this->expectException(CRM_Core_Exception_PrematureExitException::class);
    try {
      $webhook->runPublic();
    }
    finally {
      $this->assertTrue($webhook->subscriptionConfirmed);
    }
  }

  /**
   * @dataProvider unknownOrIgnoredTypeProvider
   */
  public function testUnknownOrIgnoredTypeExitsWithoutDispatch(string $type): void {
    $webhook = $this->buildWebhook(['Type' => $type]);

    $this->expectException(CRM_Core_Exception_PrematureExitException::class);
    try {
      $webhook->runPublic();
    }
    finally {
      $this->assertFalse($webhook->subscriptionConfirmed);
    }
  }

  public function unknownOrIgnoredTypeProvider(): array {
    return [
      'unrecognized type' => ['UnsubscribeConfirmation'],
      'empty string type' => [''],
    ];
  }

  /**
   * A Notification with no `mail` object must be ignored, not fataled on.
   *
   * SES publishes such notifications on the same topic as bounces and
   * complaints - pointing an identity's feedback at a topic produces a
   * "Successfully validated SNS topic" message - so this is ordinary traffic,
   * not a malformed request. Without the guard getVerpItemsFromSource()
   * dereferences the missing property and raises a TypeError under PHP 8, which
   * surfaces as a 500 and makes SNS redeliver on backoff.
   *
   * @dataProvider notificationWithoutMailPayloadProvider
   */
  public function testNotificationWithoutMailPayloadIsIgnored(string $rawMessage): void {
    // Message is passed through the envelope so it stays a raw string: the
    // payloads here are deliberately not the JSON object buildWebhook() would
    // encode for us.
    $webhook = $this->buildWebhook(['Type' => 'Notification', 'Message' => $rawMessage]);

    // civiExit() is the only acceptable outcome: a TypeError here (the bug)
    // would surface as an error rather than satisfy this expectation.
    $this->expectException(CRM_Core_Exception_PrematureExitException::class);
    $webhook->runPublic();
  }

  public function notificationWithoutMailPayloadProvider(): array {
    return [
      // The real SES topic-validation payload: valid JSON, but an object with
      // no `mail` key.
      'topic validation message' => [
        json_encode([
          'notificationType' => 'AmazonSnsSubscriptionSucceeded',
          'message' => 'You have successfully validated the SNS topic.',
        ]),
      ],
      // Defensive: a Message that is a string but not JSON decodes to NULL,
      // which reaches the same dereference.
      'message that is not json' => ['Successfully validated SNS topic.'],
      'empty object' => ['{}'],
    ];
  }

  public function testMissingReturnPathExitsForBounce(): void {
    [, , $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture();

    $message = $this->buildMessage('Bounce', $mailingID, $jobID, $queueID, $hash);
    // No commonHeaders.returnPath and no X-CiviMail-Bounce header at all.
    // (A non-empty dummy key keeps commonHeaders/headers as JSON *objects*
    // rather than arrays once decoded - matching the real SES payload shape
    // that property_exists() expects.)
    $message['mail']['commonHeaders'] = ['other' => 'value'];
    $message['mail']['headers'] = ['dummy' => 'value'];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    $this->expectException(CRM_Core_Exception_PrematureExitException::class);
    try {
      // run() destructures getVerpItemsFromSource()'s [] return into
      // [$job_id, $event_queue_id, $hash], which raises (harmless, ignored
      // in production) "undefined array key" warnings; @ keeps this test
      // focused on the actual dispatch outcome (civiExit(), no bounce
      // recorded) rather than that PHP notice.
      @$webhook->runPublic();
    }
    finally {
      $bounceCount = (int) CRM_Core_DAO::singleValueQuery(
        'SELECT COUNT(*) FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
        [1 => [$queueID, 'Integer']]
      );
      $this->assertSame(0, $bounceCount);
    }
  }

  /**
   * When the return-path carries no VERP, X-CiviMail-Bounce is the fallback
   * source for job/queue/hash. Reaching that branch needs a separator the
   * return-path does not contain, which the default '.' never satisfies for an
   * FQDN, hence '-' here.
   */
  public function testVerpRecoveredFromCiviMailBounceHeader(): void {
    Civi::settings()->set('verpSeparator', '-');
    [, $emailID, $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture();
    $email = Email::get(FALSE)->addWhere('id', '=', $emailID)->execute()->single()['email'];

    $message = $this->buildMessage('Bounce', $mailingID, $jobID, $queueID, $hash);
    // A return-path with no VERP in it, so the fallback below is consulted.
    $message['mail']['commonHeaders'] = ['returnPath' => 'noverp@' . $this->domain];
    // Real SES sends mail.headers as a LIST OF OBJECTS, which is what the
    // fallback has to iterate.
    $message['mail']['headers'] = [
      ['name' => 'Date', 'value' => 'Mon, 4 Aug 2026 09:00:00 +0000'],
      [
        'name' => 'X-CiviMail-Bounce',
        'value' => implode('-', [$this->localpart . 'b', $jobID, $queueID, $hash]) . '@' . $this->domain,
      ],
    ];
    $message['bounce'] = [
      'bounceType' => 'Permanent',
      'bounceSubType' => 'General',
      'bouncedRecipients' => [
        ['emailAddress' => $email],
      ],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      $webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      // run() always civiExit()s eventually - expected either way.
    }

    $bounceCount = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
      [1 => [$queueID, 'Integer']]
    );
    $this->assertSame(1, $bounceCount, 'The VERP is recovered from X-CiviMail-Bounce when the return-path carries none.');
  }

  public function testBounceMatchingQueueEmailRecordsBounceAndPutsEmailOnHold(): void {
    [$contactID, $emailID, $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture();
    $email = Email::get(FALSE)->addWhere('id', '=', $emailID)->execute()->single()['email'];

    $message = $this->buildMessage('Bounce', $mailingID, $jobID, $queueID, $hash);
    $message['bounce'] = [
      'bounceType' => 'Permanent',
      'bounceSubType' => 'General',
      'bouncedRecipients' => [
        ['emailAddress' => $email],
      ],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      $webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      // run() always civiExit()s eventually - expected either way.
    }

    $onHold = Email::get(FALSE)->addWhere('id', '=', $emailID)->execute()->single()['on_hold'];
    $this->assertNotEmpty($onHold, 'A matched, classifiable bounce puts the email on hold.');

    $bounceCount = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
      [1 => [$queueID, 'Integer']]
    );
    $this->assertSame(1, $bounceCount);
  }

  public function testBounceForDifferentEmailThanQueueIsNotRecorded(): void {
    [, , $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture();

    $message = $this->buildMessage('Bounce', $mailingID, $jobID, $queueID, $hash);
    $message['bounce'] = [
      'bounceType' => 'Permanent',
      'bounceSubType' => 'General',
      'bouncedRecipients' => [
        // Not the email address on this queue row.
        ['emailAddress' => 'someone-else@' . $this->domain],
      ],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    $this->expectException(CRM_Core_Exception_PrematureExitException::class);
    try {
      $webhook->runPublic();
    }
    finally {
      $bounceCount = (int) CRM_Core_DAO::singleValueQuery(
        'SELECT COUNT(*) FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
        [1 => [$queueID, 'Integer']]
      );
      $this->assertSame(0, $bounceCount, 'A bounce for an unrelated email address must not be recorded.');
    }
  }

  /**
   * @dataProvider bounceTypeProvider
   */
  public function testBounceTypeMapping(string $bounceType, string $bounceSubType, string $expectedCiviBounceType): void {
    [$contactID, $emailID, $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture();
    $email = Email::get(FALSE)->addWhere('id', '=', $emailID)->execute()->single()['email'];

    $message = $this->buildMessage('Bounce', $mailingID, $jobID, $queueID, $hash);
    $message['bounce'] = [
      'bounceType' => $bounceType,
      'bounceSubType' => $bounceSubType,
      'bouncedRecipients' => [['emailAddress' => $email]],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      $webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
    }

    $recordedTypeId = CRM_Core_DAO::singleValueQuery(
      'SELECT bounce_type_id FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
      [1 => [$queueID, 'Integer']]
    );
    $recordedTypeName = CRM_Core_DAO::singleValueQuery(
      'SELECT name FROM civicrm_mailing_bounce_type WHERE id = %1',
      [1 => [$recordedTypeId, 'Integer']]
    );
    $this->assertSame($expectedCiviBounceType, $recordedTypeName);
  }

  public function bounceTypeProvider(): array {
    return [
      'Permanent/General -> Invalid' => ['Permanent', 'General', 'Invalid'],
      'Permanent/NoEmail -> Invalid' => ['Permanent', 'NoEmail', 'Invalid'],
      'Permanent/Suppressed -> Invalid' => ['Permanent', 'Suppressed', 'Invalid'],
      'Permanent/OnAccountSuppressionList -> Invalid' => ['Permanent', 'OnAccountSuppressionList', 'Invalid'],
      'Transient/General -> Relay' => ['Transient', 'General', 'Relay'],
      'Transient/MailboxFull -> Quota' => ['Transient', 'MailboxFull', 'Quota'],
      'Transient/MessageTooLarge -> Relay' => ['Transient', 'MessageTooLarge', 'Relay'],
      'Transient/ContentRejected -> Spam' => ['Transient', 'ContentRejected', 'Spam'],
      'Transient/AttachmentRejected -> Spam' => ['Transient', 'AttachmentRejected', 'Spam'],
    ];
  }

  public function testBouncedRecipientEmailWithDisplayNameIsExtracted(): void {
    // Regression test for https://lab.civicrm.org/extensions/ses/-/issues/6 -
    // SNS docs don't show emailAddress formatted with a display name, but
    // real-world SES bounces do send e.g. `"Some Name" <foo@example.org>`.
    [$contactID, $emailID, $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture();
    $email = Email::get(FALSE)->addWhere('id', '=', $emailID)->execute()->single()['email'];

    $message = $this->buildMessage('Bounce', $mailingID, $jobID, $queueID, $hash);
    $message['bounce'] = [
      'bounceType' => 'Permanent',
      'bounceSubType' => 'General',
      'bouncedRecipients' => [
        ['emailAddress' => '"Some Name" <' . $email . '>'],
      ],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      $webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
    }

    $bounceCount = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
      [1 => [$queueID, 'Integer']]
    );
    $this->assertSame(1, $bounceCount, 'The display-name-wrapped address must still match the queue email.');
  }

  public function testBounceForSubAddressedRecipientIsRecorded(): void {
    // A sub-addressed recipient (`user+tag@example.org`) must survive
    // extraction intact. Drop `+` from the local-part class and the match
    // starts after it, yielding `tag@example.org`, which no longer equals the
    // queue email - so checkIfBouncedEmailMatchesQueueEmail() rejects a
    // perfectly good bounce and the address is never put on hold.
    [$contactID, $emailID, $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture('+tag');
    $email = Email::get(FALSE)->addWhere('id', '=', $emailID)->execute()->single()['email'];

    $message = $this->buildMessage('Bounce', $mailingID, $jobID, $queueID, $hash);
    $message['bounce'] = [
      'bounceType' => 'Permanent',
      'bounceSubType' => 'General',
      'bouncedRecipients' => [['emailAddress' => $email]],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      $webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
    }

    $bounceCount = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
      [1 => [$queueID, 'Integer']]
    );
    $this->assertSame(1, $bounceCount, 'A sub-addressed recipient must still match the queue email.');
  }

  public function testComplaintForSubAddressedRecipientDoesNotOptOutAnotherContact(): void {
    // The same extraction bug in the no-queue complaint path writes to the
    // wrong contact rather than merely dropping the event: the mangled
    // `tag@example.org` is looked up directly, so a contact who happens to own
    // that address is opted out while the actual complainant is not.
    $complainant = 'complainer+tag@' . $this->domain;
    $bystanderAddress = 'tag@' . $this->domain;

    $bystanderID = Contact::create(FALSE)->addValue('contact_type', 'Individual')->execute()->single()['id'];
    Email::create(FALSE)
      ->addValue('contact_id', $bystanderID)
      ->addValue('email', $bystanderAddress)
      ->addValue('is_primary', TRUE)
      ->execute();

    $complainantID = Contact::create(FALSE)->addValue('contact_type', 'Individual')->execute()->single()['id'];
    Email::create(FALSE)
      ->addValue('contact_id', $complainantID)
      ->addValue('email', $complainant)
      ->addValue('is_primary', TRUE)
      ->execute();

    // No VERP, so no queue row resolves and the fallback email lookup runs.
    $message = [
      'notificationType' => 'Complaint',
      'mail' => [
        'source' => 'noreply@' . $this->domain,
        'commonHeaders' => ['returnPath' => 'noreply@' . $this->domain],
        'headers' => [],
      ],
      'complaint' => [
        'complainedRecipients' => [['emailAddress' => $complainant]],
        'complaintFeedbackType' => 'abuse',
      ],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      // See testMissingReturnPathExitsForBounce for why @ is needed here.
      @$webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
    }

    $this->assertSame(0, (int) Contact::get(FALSE)
      ->addSelect('is_opt_out')->addWhere('id', '=', $bystanderID)
      ->execute()->single()['is_opt_out'],
      'A contact who did not complain must never be opted out.');
    $this->assertSame(1, (int) Contact::get(FALSE)
      ->addSelect('is_opt_out')->addWhere('id', '=', $complainantID)
      ->execute()->single()['is_opt_out'],
      'The contact who actually complained must be opted out.');
  }

  public function testComplaintMatchingQueueEmailRecordsBounceAndOptsOutContact(): void {
    [$contactID, $emailID, $mailingID, $jobID, $queueID, $hash] = $this->createQueueFixture();
    $email = Email::get(FALSE)->addWhere('id', '=', $emailID)->execute()->single()['email'];

    $message = $this->buildMessage('Complaint', $mailingID, $jobID, $queueID, $hash);
    $message['complaint'] = [
      'complainedRecipients' => [['emailAddress' => $email]],
      'complaintFeedbackType' => 'abuse',
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      $webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
    }

    $isOptOut = Contact::get(FALSE)->addWhere('id', '=', $contactID)->execute()->single()['is_opt_out'];
    $this->assertTrue($isOptOut);

    $reason = CRM_Core_DAO::singleValueQuery(
      'SELECT bounce_reason FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
      [1 => [$queueID, 'Integer']]
    );
    $this->assertStringContainsString('abuse', $reason);
  }

  public function testComplaintWithNoMatchingQueueStillOptsOutContactByEmail(): void {
    // A complaint whose returnPath/verp we can't resolve to a queue row at
    // all: run() skips straight to the Complaint branch's own DB lookup by
    // raw email address, since notificationType === 'Complaint' is exempted
    // from the earlier verp-verification guard. Covers all complainedRecipients,
    // not just the first - map_complaint_types() puts every extracted address
    // into the plural 'emailAddresses', so this loops over all of them.
    $contactID1 = Contact::create(FALSE)->addValue('contact_type', 'Individual')->execute()->single()['id'];
    $emailAddress1 = 'complainer1@' . $this->domain;
    Email::create(FALSE)
      ->addValue('contact_id', $contactID1)
      ->addValue('email', $emailAddress1)
      ->addValue('is_primary', TRUE)
      ->execute();
    $contactID2 = Contact::create(FALSE)->addValue('contact_type', 'Individual')->execute()->single()['id'];
    $emailAddress2 = 'complainer2@' . $this->domain;
    Email::create(FALSE)
      ->addValue('contact_id', $contactID2)
      ->addValue('email', $emailAddress2)
      ->addValue('is_primary', TRUE)
      ->execute();

    $message = [
      'notificationType' => 'Complaint',
      // Non-empty dummy keys keep commonHeaders/headers as JSON *objects*
      // once decoded (an empty PHP array always encodes as a JSON array),
      // matching what property_exists() expects them to be.
      'mail' => ['source' => 'noreply@' . $this->domain, 'commonHeaders' => ['other' => 'value'], 'headers' => ['dummy' => 'value']],
      'complaint' => [
        'complainedRecipients' => [
          ['emailAddress' => $emailAddress1],
          ['emailAddress' => $emailAddress2],
        ],
      ],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    try {
      // run() destructures getVerpItemsFromSource()'s [] return into
      // [$job_id, $event_queue_id, $hash], which raises (harmless, ignored
      // in production) "undefined array key" warnings; @ keeps this test
      // focused on the actual dispatch outcome rather than that PHP notice.
      @$webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
    }

    $isOptOut1 = Contact::get(FALSE)->addWhere('id', '=', $contactID1)->execute()->single()['is_opt_out'];
    $isOptOut2 = Contact::get(FALSE)->addWhere('id', '=', $contactID2)->execute()->single()['is_opt_out'];
    $this->assertTrue($isOptOut1);
    $this->assertTrue($isOptOut2);
  }

  public function testComplaintWithNoMatchingQueueAndNoMatchingContactExitsCleanly(): void {
    // No queue AND no contact found for the complained address either: must
    // still civiExit() cleanly rather than falling through to
    // checkIfBouncedEmailMatchesQueueEmail() with an empty/NULL
    // $event_queue_id, which has a typed `int $queueID` parameter and would
    // throw a TypeError.
    $message = [
      'notificationType' => 'Complaint',
      'mail' => ['source' => 'noreply@' . $this->domain, 'commonHeaders' => ['other' => 'value'], 'headers' => ['dummy' => 'value']],
      'complaint' => [
        'complainedRecipients' => [
          ['emailAddress' => 'nobody-knows-this-address@' . $this->domain],
        ],
      ],
    ];

    $webhook = $this->buildWebhook(['Type' => 'Notification'], $message);
    $this->expectException(CRM_Core_Exception_PrematureExitException::class);
    // See testMissingReturnPathExitsForBounce for why @ is needed here.
    @$webhook->runPublic();
  }

  /**
   * Helper: builds a Mailing + MailingJob + Contact + Email + MailingEventQueue
   * fixture and returns [$contactID, $emailID, $mailingID, $jobID, $queueID, $hash].
   *
   * $localPartSuffix is appended to the generated local part, for recipients
   * whose address shape matters (e.g. sub-addressing).
   */
  private function createQueueFixture(string $localPartSuffix = ''): array {
    $contactID = Contact::create(FALSE)->addValue('contact_type', 'Individual')->execute()->single()['id'];
    $email = Email::create(FALSE)
      ->addValue('contact_id', $contactID)
      ->addValue('email', 'recipient-' . $contactID . $localPartSuffix . '@' . $this->domain)
      ->addValue('is_primary', TRUE)
      ->execute()->single();

    $mailingID = Mailing::create(FALSE)
      ->addValue('name', 'Test mailing ' . $contactID)
      ->addValue('subject', 'Test subject')
      ->execute()->single()['id'];
    $jobID = MailingJob::create(FALSE)
      ->addValue('mailing_id', $mailingID)
      ->execute()->single()['id'];
    $hash = CRM_Mailing_Event_BAO_MailingEventQueue::hash();
    $queue = MailingEventQueue::create(FALSE)
      ->addValue('contact_id', $contactID)
      ->addValue('mailing_id', $mailingID)
      ->addValue('email_id', $email['id'])
      ->addValue('job_id', $jobID)
      ->addValue('hash', $hash)
      ->execute()->single();

    return [$contactID, $email['id'], $mailingID, $jobID, $queue['id'], $hash];
  }

  /**
   * Helper: builds the SES message payload (the JSON that SNS wraps as its
   * Message string) for a queue fixture, with a valid VERP returnPath.
   */
  private function buildMessage(string $notificationType, int $mailingID, int $jobID, int $queueID, string $hash): array {
    $returnPath = implode('.', [$this->localpart . 'b', $jobID, $queueID, $hash]) . '@' . $this->domain;
    return [
      'notificationType' => $notificationType,
      'mail' => [
        'source' => 'noreply@' . $this->domain,
        'commonHeaders' => ['returnPath' => $returnPath],
        'headers' => [],
      ],
    ];
  }

  /**
   * Helper: builds a CRM_Ses_Page_WebhookTestable with signature verification
   * forced to succeed, wrapping the given (decoded) message as the SNS
   * envelope's Message string.
   */
  private function buildWebhook(array $envelopeFields, ?array $message = NULL): CRM_Ses_Page_WebhookTestable {
    $event = array_merge([
      'Type' => 'Notification',
      'MessageId' => 'msg-' . uniqid(),
      'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:default-topic',
      'Message' => $message !== NULL ? json_encode($message) : json_encode(['notificationType' => 'Delivery']),
    ], $envelopeFields);

    $webhook = new CRM_Ses_Page_WebhookTestable((object) $event);
    $webhook->forceVerifySignatureResult = TRUE;
    return $webhook;
  }

}
