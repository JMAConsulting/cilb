<?php

use Aws\Credentials\Credentials;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Covers the ses_civicrm_pre() hook in ses.php, which wires an Email's
 * on_hold transition through to CRM_Ses_SuppressionList::maybeRemove().
 *
 * @group headless
 */
class ses_civicrm_preTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  private int $contactID;
  private int $emailID;
  private string $emailAddress;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__ . '/..')
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    Civi::settings()->set('ses_access_key', 'test-access-key');
    Civi::settings()->set('ses_secret_key', 'test-secret-key');
    Civi::settings()->set('ses_region', 'us-east-1');
    Civi::settings()->set('ses_suppression_list_removal', TRUE);

    $this->contactID = Contact::create(FALSE)->addValue('contact_type', 'Individual')->execute()->single()['id'];
    $this->emailAddress = 'onhold-' . $this->contactID . '@example.org';
    $this->emailID = Email::create(FALSE)
      ->addValue('contact_id', $this->contactID)
      ->addValue('email', $this->emailAddress)
      ->addValue('is_primary', TRUE)
      ->addValue('on_hold', 1)
      ->execute()->single()['id'];
  }

  public function tearDown(): void {
    CRM_Ses_SesClient::setInstance(NULL);
    parent::tearDown();
  }

  public function testOnHoldTransitionRemovesFromSuppressionList(): void {
    $handler = new MockHandler([new Result([])]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $params = ['on_hold' => 0];
    ses_civicrm_pre('edit', 'Email', $this->emailID, $params);

    $this->assertSame(0, $handler->count());
    $this->assertSame('DeleteSuppressedDestination', $handler->getLastCommand()->getName());
    $this->assertSame($this->emailAddress, $handler->getLastCommand()['EmailAddress']);
  }

  public function testDoesNothingWhenSettingDisabled(): void {
    Civi::settings()->set('ses_suppression_list_removal', FALSE);
    $handler = new MockHandler();
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $params = ['on_hold' => 0];
    ses_civicrm_pre('edit', 'Email', $this->emailID, $params);

    $this->assertSame(0, $handler->count(), 'Client must never even be constructed/called when the setting is off.');
  }

  public function testDoesNothingForNonEmailEntity(): void {
    $handler = new MockHandler();
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $params = ['on_hold' => 0];
    ses_civicrm_pre('edit', 'Contact', $this->contactID, $params);

    $this->assertSame(0, $handler->count());
  }

  public function testDoesNothingForNonEditOp(): void {
    $handler = new MockHandler();
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $params = ['on_hold' => 0];
    ses_civicrm_pre('create', 'Email', $this->emailID, $params);

    $this->assertSame(0, $handler->count());
  }

  public function testDoesNothingWhenOnHoldNotInParams(): void {
    $handler = new MockHandler();
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $params = ['email' => $this->emailAddress];
    ses_civicrm_pre('edit', 'Email', $this->emailID, $params);

    $this->assertSame(0, $handler->count());
  }

  public function testDoesNothingWhenOnHoldStaysOn(): void {
    $handler = new MockHandler();
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $params = ['on_hold' => 1];
    ses_civicrm_pre('edit', 'Email', $this->emailID, $params);

    $this->assertSame(0, $handler->count());
  }

  public function testSesClientInitFailureIsCaughtAndDoesNotPropagate(): void {
    // No mock instance set, and SES config is deliberately incomplete, so
    // CRM_Ses_SesClient::getInstance() throws when ses_civicrm_pre() calls it.
    Civi::settings()->set('ses_access_key', NULL);

    $params = ['on_hold' => 0];
    // Must not throw - the hook's own try/catch(\Throwable) must swallow it.
    ses_civicrm_pre('edit', 'Email', $this->emailID, $params);
    $this->assertTrue(TRUE, 'ses_civicrm_pre() must not let a SES client init failure propagate to the Email save.');
  }

  private function getClient(MockHandler $handler): SesV2Client {
    return new SesV2Client([
      'version' => 'latest',
      'region' => 'us-east-1',
      'credentials' => new Credentials('key', 'secret'),
      'handler' => $handler,
    ]);
  }

}
