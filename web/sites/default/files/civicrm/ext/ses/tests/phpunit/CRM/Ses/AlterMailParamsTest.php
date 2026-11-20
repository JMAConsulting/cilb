<?php

use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Api4\MailingEventQueue;
use Civi\Api4\MailSettings;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

require_once __DIR__ . '/Page/WebhookTestable.php';

/**
 * Covers the VERP returnPath ses_civicrm_alterMailParams() puts on
 * transactional emails.
 *
 * @group headless
 */
class CRM_Ses_AlterMailParamsTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

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
      ->installMe(__DIR__ . '/..')
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
    CRM_Ses::createTransactionalMailing();
  }

  /**
   * The transactional VERP has to be built the same way core builds the bulk
   * ones (CRM_Mailing_BAO_Mailing::getVerpAndUrls(), which prefixes the
   * localpart to the type character), because the webhook recovers the verp
   * items by stripping a localpart-length prefix off the returnPath.
   */
  public function testTransactionalReturnPathIncludesLocalpart(): void {
    [$contactID, $emailAddress] = $this->createContactWithEmail();

    $params = ['contact_id' => $contactID, 'toEmail' => $emailAddress];
    ses_civicrm_alterMailParams($params, 'singleEmail');

    $queue = $this->getQueueForContact($contactID);
    $expected = $this->localpart . 'm.' . $queue['job_id'] . '.' . $queue['id'] . '.' . $queue['hash'] . '@' . $this->domain;
    $this->assertSame($expected, $params['returnPath']);
  }

  /**
   * End-to-end: a bounce reported against the returnPath we generated has to
   * be attributable back to the queue row it was generated from. A returnPath
   * missing the localpart parses into the wrong verp items and the bounce is
   * silently dropped.
   */
  public function testTransactionalReturnPathIsParseableByWebhook(): void {
    [$contactID, $emailAddress] = $this->createContactWithEmail();

    $params = ['contact_id' => $contactID, 'toEmail' => $emailAddress];
    ses_civicrm_alterMailParams($params, 'singleEmail');
    $queueID = $this->getQueueForContact($contactID)['id'];

    $event = (object) [
      'Type' => 'Notification',
      'MessageId' => 'msg-' . uniqid(),
      'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:default-topic',
      'Message' => json_encode([
        'notificationType' => 'Bounce',
        'mail' => [
          'source' => 'noreply@' . $this->domain,
          'commonHeaders' => ['returnPath' => $params['returnPath']],
          'headers' => [],
        ],
        'bounce' => [
          'bounceType' => 'Permanent',
          'bounceSubType' => 'General',
          'bouncedRecipients' => [['emailAddress' => $emailAddress]],
        ],
      ]),
    ];
    $webhook = new CRM_Ses_Page_WebhookTestable($event);
    $webhook->forceVerifySignatureResult = TRUE;
    try {
      // @ so a returnPath the parser can't resolve fails on the bounce
      // assertion below rather than on the "undefined array key" warning
      // run() raises when it destructures a short verp items array.
      @$webhook->runPublic();
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      // run() always civiExit()s eventually - expected either way.
    }

    $bounceCount = (int) CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_mailing_event_bounce WHERE event_queue_id = %1',
      [1 => [$queueID, 'Integer']]
    );
    $this->assertSame(1, $bounceCount);
  }

  /**
   * Helper: returns [$contactID, $emailAddress].
   */
  private function createContactWithEmail(): array {
    $contactID = Contact::create(FALSE)->addValue('contact_type', 'Individual')->execute()->single()['id'];
    $emailAddress = 'recipient-' . $contactID . '@' . $this->domain;
    Email::create(FALSE)
      ->addValue('contact_id', $contactID)
      ->addValue('email', $emailAddress)
      ->addValue('is_primary', TRUE)
      ->execute();

    return [$contactID, $emailAddress];
  }

  /**
   * Helper: the queue row alterMailParams() created for this recipient.
   */
  private function getQueueForContact(int $contactID): array {
    return MailingEventQueue::get(FALSE)
      ->addSelect('id', 'job_id', 'hash')
      ->addWhere('contact_id', '=', $contactID)
      ->execute()
      ->single();
  }

}
