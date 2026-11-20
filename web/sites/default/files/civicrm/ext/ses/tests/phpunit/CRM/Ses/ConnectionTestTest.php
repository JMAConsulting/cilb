<?php

use Aws\Command;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Covers CRM_Ses_ConnectionTest, which probes an SES account when the settings
 * are saved.
 *
 * @group headless
 */
class CRM_Ses_ConnectionTestTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__ . '/..')
      ->apply();
  }

  public function tearDown(): void {
    CRM_Ses_SesClient::setInstance(NULL);
    parent::tearDown();
  }

  /**
   * A signature-level rejection is the one result that means the credentials
   * themselves are wrong.
   *
   * @dataProvider signatureErrorProvider
   */
  public function testSignatureErrorsMeanCredentialsRejected(string $errorCode): void {
    $handler = new MockHandler([
      new AwsException('nope', new Command('GetAccount'), ['code' => $errorCode]),
    ]);

    $result = CRM_Ses_ConnectionTest::run($this->getClient($handler), TRUE);

    $this->assertSame(CRM_Ses_ConnectionTest::CREDENTIALS_REJECTED, $result['credentials']);
    $this->assertSame($errorCode, $result['credentialsDetail']);
    $this->assertSame(
      CRM_Ses_ConnectionTest::PERMISSION_SKIPPED,
      $result['suppressionPermission'],
      'There is no point probing permissions once the credentials are known bad.'
    );
    // Only the first mock response is consumed - the suppression probe is skipped.
    $this->assertSame(0, $handler->count());
  }

  public function signatureErrorProvider(): array {
    return [
      ['InvalidSignatureException'],
      ['SignatureDoesNotMatch'],
      ['InvalidClientTokenId'],
      ['UnrecognizedClientException'],
    ];
  }

  /**
   * The whole point of probing with GetAccount: AccessDenied proves the
   * signature verified, so the credentials are good even on a policy that
   * grants nothing but sending.
   */
  public function testAccessDeniedOnGetAccountStillMeansCredentialsAreValid(): void {
    $handler = new MockHandler([
      new AwsException('no', new Command('GetAccount'), ['code' => 'AccessDeniedException']),
      new AwsException('no such address', new Command('DeleteSuppressedDestination'), ['code' => 'NotFoundException']),
    ]);

    $result = CRM_Ses_ConnectionTest::run($this->getClient($handler), TRUE);

    $this->assertSame(CRM_Ses_ConnectionTest::CREDENTIALS_OK, $result['credentials']);
    $this->assertSame(CRM_Ses_ConnectionTest::PERMISSION_GRANTED, $result['suppressionPermission']);
  }

  /**
   * NotFoundException means the delete call was authorised and the probe
   * address simply was not suppressed - which is the expected outcome.
   */
  public function testNotFoundOnProbeMeansPermissionGranted(): void {
    $handler = new MockHandler([
      new Result([]),
      new AwsException('not found', new Command('DeleteSuppressedDestination'), ['code' => 'NotFoundException']),
    ]);

    $result = CRM_Ses_ConnectionTest::run($this->getClient($handler), TRUE);

    $this->assertSame(CRM_Ses_ConnectionTest::CREDENTIALS_OK, $result['credentials']);
    $this->assertSame(CRM_Ses_ConnectionTest::PERMISSION_GRANTED, $result['suppressionPermission']);
  }

  public function testAccessDeniedOnProbeMeansPermissionDenied(): void {
    $handler = new MockHandler([
      new Result([]),
      new AwsException('denied', new Command('DeleteSuppressedDestination'), ['code' => 'AccessDeniedException']),
    ]);

    $result = CRM_Ses_ConnectionTest::run($this->getClient($handler), TRUE);

    $this->assertSame(CRM_Ses_ConnectionTest::CREDENTIALS_OK, $result['credentials']);
    $this->assertSame(CRM_Ses_ConnectionTest::PERMISSION_DENIED, $result['suppressionPermission']);
  }

  /**
   * The probe must never actually remove anything real: it has to target an
   * address that cannot be a genuine suppression entry.
   */
  public function testSuppressionProbeUsesADisposableReservedAddress(): void {
    $seen = [];
    $handler = new MockHandler([
      new Result([]),
      function ($command) use (&$seen) {
        $seen[] = $command['EmailAddress'];
        return new AwsException('not found', $command, ['code' => 'NotFoundException']);
      },
    ]);

    CRM_Ses_ConnectionTest::run($this->getClient($handler), TRUE);

    $this->assertCount(1, $seen);
    $this->assertStringEndsWith('@example.org', $seen[0], 'example.org is IANA-reserved and cannot receive mail.');
    $this->assertMatchesRegularExpression('/^civicrm-ses-permission-probe-[0-9a-f]{16}@/', $seen[0]);
  }

  /**
   * Not enabled means not probed - no call should be made at all.
   */
  public function testSuppressionProbeSkippedWhenFeatureDisabled(): void {
    $handler = new MockHandler([new Result([])]);

    $result = CRM_Ses_ConnectionTest::run($this->getClient($handler), FALSE);

    $this->assertSame(CRM_Ses_ConnectionTest::CREDENTIALS_OK, $result['credentials']);
    $this->assertSame(CRM_Ses_ConnectionTest::PERMISSION_SKIPPED, $result['suppressionPermission']);
    $this->assertSame(0, $handler->count());
  }

  /**
   * A denied permission has to be reported: at runtime
   * CRM_Ses_SuppressionList::maybeRemove() swallows the failure, so this
   * message is the only place a site is told.
   */
  public function testDeniedPermissionIsReportedAlongsideValidCredentials(): void {
    $messages = CRM_Ses_ConnectionTest::describe([
      'credentials' => CRM_Ses_ConnectionTest::CREDENTIALS_OK,
      'credentialsDetail' => NULL,
      'suppressionPermission' => CRM_Ses_ConnectionTest::PERMISSION_DENIED,
      'suppressionDetail' => NULL,
    ]);

    $this->assertCount(2, $messages);
    $this->assertSame('success', $messages[0][2]);
    $this->assertStringContainsString('cannot be verified without sending', $messages[0][0]);
    $this->assertSame('warning', $messages[1][2]);
    $this->assertStringContainsString('ses:DeleteSuppressedDestination', $messages[1][0]);
  }

  /**
   * Rejected credentials must not also claim anything reassuring.
   */
  public function testRejectedCredentialsProduceOnlyAnError(): void {
    $messages = CRM_Ses_ConnectionTest::describe([
      'credentials' => CRM_Ses_ConnectionTest::CREDENTIALS_REJECTED,
      'credentialsDetail' => 'InvalidSignatureException',
      'suppressionPermission' => CRM_Ses_ConnectionTest::PERMISSION_SKIPPED,
      'suppressionDetail' => NULL,
    ]);

    $this->assertCount(1, $messages);
    $this->assertSame('error', $messages[0][2]);
    $this->assertStringContainsString('InvalidSignatureException', $messages[0][0]);
    $this->assertStringContainsString('SMTP password', $messages[0][0]);
  }

  /**
   * An unconfigured site must not be nagged, and must not error.
   */
  public function testReportToSessionIsSilentWhenNotConfigured(): void {
    $this->drainStatus();
    Civi::settings()->set('ses_access_key', NULL);
    Civi::settings()->set('ses_secret_key', NULL);
    Civi::settings()->set('ses_region', NULL);

    CRM_Ses_ConnectionTest::reportToSession();

    $this->assertEmpty($this->drainStatus());
  }

  /**
   * The reporting glue, not just the probe: rejected credentials must reach the
   * user as a single error message.
   */
  public function testReportToSessionQueuesAnErrorForRejectedCredentials(): void {
    $this->drainStatus();
    $handler = new MockHandler([
      new AwsException('nope', new Command('GetAccount'), ['code' => 'InvalidSignatureException']),
    ]);

    CRM_Ses_ConnectionTest::reportToSession($this->getClient($handler));

    $status = $this->drainStatus();
    $this->assertCount(1, $status);
    $this->assertSame('error', $status[0]['type']);
    $this->assertStringContainsString('InvalidSignatureException', $status[0]['text']);
  }

  /**
   * Session status is global and is not rolled back with the test transaction,
   * so drain whatever is already queued before measuring, and return only what
   * the code under test added.
   */
  private function drainStatus(): array {
    return CRM_Core_Session::singleton()->getStatus(TRUE);
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
