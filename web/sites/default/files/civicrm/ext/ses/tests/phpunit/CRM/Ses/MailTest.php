<?php

use Aws\Command;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

require_once __DIR__ . '/MailTestable.php';

// Buildkit's civicrm.settings.d/100-mail.php defines CIVICRM_MAIL_LOG for any
// process without a configured mailing_backend (which includes this test
// process), which makes send() log-and-return-TRUE before ever reaching the
// SES client. Defining CIVICRM_MAIL_LOG_AND_SEND makes it log *and* continue,
// so these tests exercise the real SES-calling logic.
if (!defined('CIVICRM_MAIL_LOG_AND_SEND')) {
  define('CIVICRM_MAIL_LOG_AND_SEND', 1);
}

/**
 * @group headless
 */
class CRM_Ses_MailTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    Civi::settings()->set('ses_access_key', 'test-access-key');
    Civi::settings()->set('ses_secret_key', 'test-secret-key');
    Civi::settings()->set('ses_region', 'us-east-1');
  }

  public function tearDown(): void {
    CRM_Ses_SesClient::setInstance(NULL);
    parent::tearDown();
  }

  /**
   * @dataProvider missingConfigProvider
   */
  public function testCheckConfigThrowsWhenSettingMissing(string $missingSetting, string $expectedMessageFragment): void {
    Civi::settings()->set($missingSetting, NULL);
    $mail = new CRM_Ses_Mail();

    $this->expectException(Exception::class);
    $this->expectExceptionMessage($expectedMessageFragment);
    $mail->checkConfig();
  }

  public function missingConfigProvider(): array {
    return [
      'missing access key' => ['ses_access_key', 'No API key defined'],
      'missing secret key' => ['ses_secret_key', 'No API secret defined'],
      'missing region' => ['ses_region', 'No Region defined'],
    ];
  }

  public function testCheckConfigPassesWhenAllSettingsPresent(): void {
    $mail = new CRM_Ses_Mail();
    $this->assertTrue($mail->checkConfig());
  }

  public function testSendSucceedsOnFirstAttempt(): void {
    $handler = new MockHandler([new Result(['MessageId' => 'abc123'])]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $mail = new CRM_Ses_Mail();
    $result = $mail->send('to@example.org', ['From' => 'from@example.org', 'To' => 'to@example.org', 'Subject' => 'Test'], 'body');

    $this->assertNotInstanceOf(PEAR_Error::class, $result);
    $this->assertSame(0, $handler->count());
  }

  public function testSendRetriesOnThrottlingThenSucceeds(): void {
    $throttled = new AwsException('Rate exceeded', new Command('SendEmail'), ['code' => 'Throttling']);
    $handler = new MockHandler([$throttled, new Result(['MessageId' => 'abc123'])]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    // The real CRM_Ses_Mail (not the shrunk test double) - only one retry
    // is needed here, so the real ~50ms delay is negligible.
    $mail = new CRM_Ses_Mail();
    $result = $mail->send('to@example.org', ['From' => 'from@example.org', 'To' => 'to@example.org', 'Subject' => 'Test'], 'body');

    $this->assertNotInstanceOf(PEAR_Error::class, $result);
    $this->assertSame(0, $handler->count(), 'Both the failed and successful attempt must have been consumed.');
  }

  public function testSendGivesUpAfterExhaustingRetries(): void {
    // CRM_Ses_MailTestable shrinks maxRetries to 3 and the backoff to 1us,
    // so this exercises the "give up" path without waiting out the real
    // ~25s of exponential backoff the production 10-retry budget implies.
    $throttled = fn() => new AwsException('Rate exceeded', new Command('SendEmail'), ['code' => 'Throttling']);
    $handler = new MockHandler([$throttled(), $throttled(), $throttled()]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $mail = new CRM_Ses_MailTestable();
    $result = $mail->send('to@example.org', ['From' => 'from@example.org', 'To' => 'to@example.org', 'Subject' => 'Test'], 'body');

    $this->assertInstanceOf(PEAR_Error::class, $result);
    $this->assertSame(0, $handler->count(), 'All 3 throttled attempts must have been consumed before giving up.');
  }

  public function testSendReturnsErrorImmediatelyOnNonThrottlingException(): void {
    $handler = new MockHandler([
      new AwsException('Message rejected', new Command('SendEmail'), ['code' => 'MessageRejected']),
    ]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $mail = new CRM_Ses_Mail();
    $result = $mail->send('to@example.org', ['From' => 'from@example.org', 'To' => 'to@example.org', 'Subject' => 'Test'], 'body');

    $this->assertInstanceOf(PEAR_Error::class, $result);
    $this->assertSame(0, $handler->count());
  }

  public function testBccInsertedAfterToWhenOnlyToHeaderPresent(): void {
    $handler = new MockHandler([new Result(['MessageId' => 'abc123'])]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $mail = new CRM_Ses_Mail();
    $mail->send(
      ['to@example.org', 'bcc@example.org'],
      ['From' => 'from@example.org', 'To' => 'to@example.org', 'Subject' => 'Test'],
      'body'
    );

    $rawData = $handler->getLastCommand()['Content']['Raw']['Data'];
    $this->assertStringContainsString('Bcc: bcc@example.org', $rawData);
    $this->assertGreaterThan(strpos($rawData, 'To:'), strpos($rawData, 'Bcc:'), 'Bcc must be inserted after To.');
  }

  public function testBccInsertedAfterCcWhenBothToAndCcPresent(): void {
    $handler = new MockHandler([new Result(['MessageId' => 'abc123'])]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $mail = new CRM_Ses_Mail();
    $mail->send(
      ['to@example.org', 'cc@example.org', 'bcc@example.org'],
      ['From' => 'from@example.org', 'To' => 'to@example.org', 'Cc' => 'cc@example.org', 'Subject' => 'Test'],
      'body'
    );

    $rawData = $handler->getLastCommand()['Content']['Raw']['Data'];
    $this->assertStringContainsString('Bcc: bcc@example.org', $rawData);
    $this->assertGreaterThan(strpos($rawData, 'Cc:'), strpos($rawData, 'Bcc:'), 'Bcc must be inserted after Cc, not To.');
  }

  public function testBccInsertedAfterFromWhenNeitherToNorCcPresent(): void {
    $handler = new MockHandler([new Result(['MessageId' => 'abc123'])]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $mail = new CRM_Ses_Mail();
    // Bcc-reinsertion only runs when $recipients is an array (send() gates
    // the whole block on is_array($recipients)) - a single-element array
    // with neither To nor Cc in $headers means that element is Bcc.
    $mail->send(
      ['bcc@example.org'],
      ['From' => 'from@example.org', 'Subject' => 'Test'],
      'body'
    );

    $rawData = $handler->getLastCommand()['Content']['Raw']['Data'];
    $this->assertStringContainsString('Bcc: bcc@example.org', $rawData);
    $this->assertGreaterThan(strpos($rawData, 'From:'), strpos($rawData, 'Bcc:'));
  }

  public function testNoBccHeaderInsertedWhenNoBccRecipient(): void {
    $handler = new MockHandler([new Result(['MessageId' => 'abc123'])]);
    CRM_Ses_SesClient::setInstance($this->getClient($handler));

    $mail = new CRM_Ses_Mail();
    $mail->send(
      ['to@example.org'],
      ['From' => 'from@example.org', 'To' => 'to@example.org', 'Subject' => 'Test'],
      'body'
    );

    $rawData = $handler->getLastCommand()['Content']['Raw']['Data'];
    $this->assertStringNotContainsString('Bcc:', $rawData);
  }

  /**
   * @dataProvider formatRecipientsProvider
   */
  public function testFormatRecipients($recipients, array $expected): void {
    $mail = new CRM_Ses_Mail();
    $this->assertSame($expected, $mail->formatRecipients($recipients));
  }

  public function formatRecipientsProvider(): array {
    return [
      'single plain address (string input)' => [
        'foo@example.org',
        ['foo@example.org'],
      ],
      'simple display name' => [
        ['Nicolas Ganivet <nicolas@example.org>'],
        ['"Nicolas Ganivet" <nicolas@example.org>'],
      ],
      'already-quoted display name with embedded comma' => [
        ['"Ganivet, Nicolas" <nicolas@example.org>'],
        ['"Ganivet, Nicolas" <nicolas@example.org>'],
      ],
      'multiple addresses in one string' => [
        ['nicolas@example.org, "Ganivet, Nicolas" <nicolas@example.org>'],
        ['nicolas@example.org', '"Ganivet, Nicolas" <nicolas@example.org>'],
      ],
      'multiple input strings' => [
        ['foo@example.org', 'bar@example.org'],
        ['foo@example.org', 'bar@example.org'],
      ],
    ];
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
