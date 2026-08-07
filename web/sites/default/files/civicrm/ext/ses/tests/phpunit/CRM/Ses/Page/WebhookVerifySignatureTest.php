<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

require_once __DIR__ . '/WebhookTestable.php';

/**
 * Covers CRM_Ses_Page_Webhook::verify_signature(), including the SigningCertURL
 * host-validation and TopicArn allow-list hardening added to close a pre-auth
 * SSRF (the webhook is a public, unauthenticated endpoint that used to fetch
 * an attacker-controlled URL to verify the SNS signature).
 *
 * @group headless
 */
class CRM_Ses_Page_WebhookVerifySignatureTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * @var resource|\OpenSSLAsymmetricKey
   */
  private $privateKey;

  private string $certPem;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    Civi::settings()->set('ses_sns_topic_arn', NULL);
    [$this->privateKey, $this->certPem] = $this->generateSelfSignedKeyPair();
  }

  /**
   * Happy path - proves the real openssl_sign/openssl_verify round trip
   * works, not just that bad input is rejected.
   */
  public function testValidNotificationSignatureVerifies(): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    $this->assertTrue($webhook->verifySignature());
  }

  public function testValidSubscriptionConfirmationSignatureVerifies(): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'SubscriptionConfirmation',
      'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/subscribe',
      'Token' => 'test-token',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    $this->assertTrue($webhook->verifySignature());
  }

  public function testValidSignatureVerifiesWithChinaPartitionCertHost(): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'SigningCertURL' => 'https://sns.cn-north-1.amazonaws.com.cn/cert.pem',
    ]);
    $this->assertTrue($webhook->verifySignature());
  }

  public function testTamperedMessageFailsSignatureVerification(): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    // Mutate a signed field after signing - a genuine tamper attempt.
    $webhook->_setEventField('Message', '{"tampered":true}');
    $this->assertFalse($webhook->verifySignature());
  }

  /**
   * SigningCertURL host validation (closes the pre-auth SSRF).
   *
   * @dataProvider untrustedCertUrlProvider
   */
  public function testUntrustedSigningCertUrlIsRejectedWithoutFetching(string $certUrl): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'SigningCertURL' => $certUrl,
    ]);
    $this->assertFalse($webhook->verifySignature());
    $this->assertSame(0, $webhook->certFetchAttempts, 'The cert must never be fetched for an untrusted URL - that is the whole point of the host check.');
  }

  public function untrustedCertUrlProvider(): array {
    return [
      'http (non-TLS) downgrade' => ['http://sns.us-east-1.amazonaws.com/cert.pem'],
      'unrelated host' => ['https://evil.com/cert.pem'],
      'userinfo bypass' => ['https://sns.us-east-1.amazonaws.com@evil.com/cert.pem'],
      'subdomain-suffix bypass' => ['https://sns.us-east-1.amazonaws.com.evil.com/cert.pem'],
      'missing host entirely' => ['not-a-url'],
    ];
  }

  /**
   * TopicArn allow-list.
   */
  public function testTopicArnAllowListDoesNotApplyWhenUnset(): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:whatever-topic',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    $this->assertTrue($webhook->verifySignature(), 'With no configured allow-list, any TopicArn is accepted (previous behaviour).');
  }

  public function testTopicArnAllowListAcceptsMatchingTopic(): void {
    Civi::settings()->set('ses_sns_topic_arn', 'arn:aws:sns:us-east-1:123456789012:my-topic');
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:my-topic',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    $this->assertTrue($webhook->verifySignature());
  }

  public function testTopicArnAllowListRejectsMismatchedTopic(): void {
    Civi::settings()->set('ses_sns_topic_arn', 'arn:aws:sns:us-east-1:123456789012:my-topic');
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'TopicArn' => 'arn:aws:sns:us-east-1:999999999999:someone-elses-topic',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    $this->assertFalse($webhook->verifySignature());
    $this->assertSame(0, $webhook->certFetchAttempts, 'A mismatched TopicArn is rejected before the (more expensive) signature check runs.');
  }

  public function testTopicArnAllowListAlsoBlocksSubscriptionConfirmation(): void {
    // This is the specific scenario the allow-list exists for: without it
    // gating BEFORE the Type switch, a validly-signed SubscriptionConfirmation
    // for any topic would auto-confirm the webhook's subscription to it.
    Civi::settings()->set('ses_sns_topic_arn', 'arn:aws:sns:us-east-1:123456789012:my-topic');
    $webhook = $this->buildSignedWebhook([
      'Type' => 'SubscriptionConfirmation',
      'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/subscribe',
      'Token' => 'test-token',
      'TopicArn' => 'arn:aws:sns:us-east-1:999999999999:someone-elses-topic',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);

    try {
      $webhook->runPublic();
      $this->fail('run() should civiExit().');
    }
    catch (CRM_Core_Exception_PrematureExitException $e) {
      // Expected - run() exits either way; what matters is the assertion below.
    }
    $this->assertFalse($webhook->subscriptionConfirmed, 'A mismatched-topic SubscriptionConfirmation must never be auto-confirmed.');
  }

  /**
   * Non-string field hardening.
   *
   * @dataProvider nonStringFieldProvider
   */
  public function testNonStringCriticalFieldIsRejectedSafely(string $field): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    // Replace a signed-over field with a non-string after signing - simulates
    // a hostile POST body sending the wrong JSON type for that key.
    $webhook->_setEventField($field, ['unexpected' => 'array']);
    $this->assertFalse($webhook->verifySignature());
  }

  public function nonStringFieldProvider(): array {
    return [
      ['Message'],
      ['Signature'],
      ['SigningCertURL'],
      ['TopicArn'],
    ];
  }

  /**
   * run()'s entry guard against non-object bodies.
   *
   * @dataProvider nonObjectEventProvider
   */
  public function testRunExitsGracefullyForNonObjectBody($rawEvent): void {
    $webhook = new CRM_Ses_Page_WebhookTestable();
    $webhook->setRawEvent($rawEvent);

    $this->expectException(CRM_Core_Exception_PrematureExitException::class);
    $webhook->runPublic();
  }

  public function nonObjectEventProvider(): array {
    return [
      'integer' => [5],
      'boolean' => [TRUE],
      'null (e.g. invalid JSON)' => [NULL],
      'array' => [['Type' => 'Notification']],
    ];
  }

  /**
   * Missing Signature/SigningCertURL (pre-existing behaviour, unaffected by
   * the hardening).
   */
  public function testMissingSignatureFailsCleanly(): void {
    $webhook = $this->buildSignedWebhook([
      'Type' => 'Notification',
      'SigningCertURL' => 'https://sns.us-east-1.amazonaws.com/cert.pem',
    ]);
    $webhook->_setEventField('Signature', NULL);
    $this->assertFalse($webhook->verifySignature());
    $this->assertSame(0, $webhook->certFetchAttempts);
  }

  /**
   * Helper: builds a CRM_Ses_Page_WebhookTestable whose snsEvent is genuinely signed
   * by a locally-generated test keypair, wired to serve that keypair's
   * certificate in place of a real network fetch.
   */
  private function buildSignedWebhook(array $fields): CRM_Ses_Page_WebhookTestable {
    $type = $fields['Type'];
    $keysToSign = $type === 'SubscriptionConfirmation'
      ? ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type']
      : ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];

    $event = array_merge([
      'Message' => json_encode(['notificationType' => 'Delivery']),
      'MessageId' => 'msg-' . uniqid(),
      'Subject' => 'Amazon SES Email Event Notification',
      'Timestamp' => gmdate('Y-m-d\TH:i:s.000\Z'),
      'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:default-topic',
      'Type' => $type,
    ], $fields);

    $messageToSign = '';
    foreach ($keysToSign as $key) {
      if (isset($event[$key]) && is_string($event[$key])) {
        $messageToSign .= "{$key}\n{$event[$key]}\n";
      }
    }
    openssl_sign($messageToSign, $binarySignature, $this->privateKey, OPENSSL_ALGO_SHA1);
    $event['Signature'] = base64_encode($binarySignature);

    $webhook = new CRM_Ses_Page_WebhookTestable((object) $event);
    $webhook->testCertPem = $this->certPem;
    return $webhook;
  }

  /**
   * @return array{0: \OpenSSLAsymmetricKey|resource, 1: string} [$privateKey, $certPem]
   */
  private function generateSelfSignedKeyPair(): array {
    $privateKey = openssl_pkey_new([
      'private_key_bits' => 2048,
      'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $csr = openssl_csr_new(['commonName' => 'sns.amazonaws.com (test fixture)'], $privateKey);
    $cert = openssl_csr_sign($csr, NULL, $privateKey, 1);
    openssl_x509_export($cert, $certPem);
    return [$privateKey, $certPem];
  }

}
