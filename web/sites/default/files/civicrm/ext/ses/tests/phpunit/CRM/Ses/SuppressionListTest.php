<?php

use Aws\Command;
use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\SesV2Client;

/**
 * @group headless
 */
class CRM_Ses_SuppressionListTest extends \PHPUnit\Framework\TestCase {

  /**
   * @dataProvider shouldRemoveProvider
   */
  public function testShouldRemove($oldOnHold, $newOnHold, bool $expected): void {
    $this->assertSame($expected, CRM_Ses_SuppressionList::shouldRemove($oldOnHold, $newOnHold));
  }

  public function shouldRemoveProvider(): array {
    return [
      'on -> off (int)' => [1, 0, TRUE],
      'on -> off (string)' => ['1', '0', TRUE],
      'off -> off' => [0, 0, FALSE],
      'on -> on' => [1, 1, FALSE],
      'off -> on' => [0, 1, FALSE],
      'null -> off' => [NULL, 0, FALSE],
    ];
  }

  public function testMaybeRemoveDoesNothingWhenDisabled(): void {
    $handler = new MockHandler();
    $client = $this->getClient($handler);

    CRM_Ses_SuppressionList::maybeRemove('bounced@example.org', 1, 0, FALSE, $client);

    $this->assertCount(0, $handler, 'Client must not be called when the feature is disabled.');
  }

  public function testMaybeRemoveDoesNothingWhenNoTransition(): void {
    $handler = new MockHandler();
    $client = $this->getClient($handler);

    CRM_Ses_SuppressionList::maybeRemove('bounced@example.org', 0, 0, TRUE, $client);

    $this->assertCount(0, $handler, 'Client must not be called when on_hold did not transition from 1 to 0.');
  }

  public function testMaybeRemoveCallsClientOnHappyPath(): void {
    $handler = new MockHandler([new Result([])]);
    $client = $this->getClient($handler);

    CRM_Ses_SuppressionList::maybeRemove('bounced@example.org', 1, 0, TRUE, $client);

    $this->assertSame(0, $handler->count(), 'The single queued result must have been consumed.');
    $this->assertSame('DeleteSuppressedDestination', $handler->getLastCommand()->getName());
    $this->assertSame('bounced@example.org', $handler->getLastCommand()['EmailAddress']);
  }

  public function testMaybeRemoveSwallowsClientExceptionAndLogsIt(): void {
    $exception = new AwsException('boom', new Command('DeleteSuppressedDestination'), ['code' => 'ServiceUnavailable']);
    $handler = new MockHandler([$exception]);
    $client = $this->getClient($handler);

    $loggedMessages = [];
    $logError = function (string $message) use (&$loggedMessages) {
      $loggedMessages[] = $message;
    };

    CRM_Ses_SuppressionList::maybeRemove('bounced@example.org', 1, 0, TRUE, $client, $logError);

    $this->assertCount(1, $loggedMessages);
    $this->assertStringContainsString('bounced@example.org', $loggedMessages[0]);
    $this->assertStringContainsString('boom', $loggedMessages[0]);
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
