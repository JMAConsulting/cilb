<?php

/**
 * Test double for CRM_Ses_Page_Webhook.
 *
 * The real constructor reads php://input directly, which isn't mockable, so
 * this subclass builds the object from an already-decoded event instead and
 * exposes the otherwise-protected signature verification for direct testing.
 */
class CRM_Ses_Page_WebhookTestable extends CRM_Ses_Page_Webhook {

  /**
   * @var string|null
   */
  public $testCertPem;

  /**
   * @var bool
   */
  public $subscriptionConfirmed = FALSE;

  /**
   * @var int
   */
  public $certFetchAttempts = 0;

  /**
   * When set, verify_signature() returns this instead of doing real signature
   * verification - lets dispatch/business-logic tests use fixtures that
   * aren't genuinely signed, since that path is covered separately.
   *
   * @var bool|null
   */
  public $forceVerifySignatureResult;

  public function __construct(?object $snsEvent = NULL) {
    $this->client = new GuzzleHttp\Client();
    $this->verp_separator = Civi::settings()->get('verpSeparator');
    $this->localpart = CRM_Core_BAO_MailSettings::defaultLocalpart();
    $this->civi_bounce_types = $this->get_civi_bounce_types();
    $this->snsEvent = $snsEvent;
    $this->snsEventMessage = (is_object($this->snsEvent) && isset($this->snsEvent->Message) && is_string($this->snsEvent->Message))
      ? json_decode($this->snsEvent->Message)
      : NULL;

    CRM_Core_Page::__construct();
  }

  public function verifySignature(): bool {
    return $this->verify_signature();
  }

  protected function verify_signature() {
    if ($this->forceVerifySignatureResult !== NULL) {
      return $this->forceVerifySignatureResult;
    }
    return parent::verify_signature();
  }

  public function runPublic(): void {
    $this->run();
  }

  /**
   * Directly overwrites a single field on the decoded event, e.g. to tamper
   * with an already-signed message or to inject a non-string value for a
   * normally-string field.
   */
  public function _setEventField(string $key, $value): void {
    $this->snsEvent->$key = $value;
  }

  /**
   * Sets $this->snsEvent to an arbitrary (possibly non-object) value, to
   * exercise run()'s `!is_object($this->snsEvent)` guard - not reachable via
   * the constructor, which is typed to accept an object.
   */
  public function setRawEvent($value): void {
    $this->snsEvent = $value;
  }

  /**
   * Overridden so tests can prove SubscriptionConfirmation was (or was not)
   * acted on without making a real HTTP request to a SubscribeURL.
   */
  protected function confirm_subscription() {
    $this->subscriptionConfirmed = TRUE;
  }

  /**
   * Overridden so tests never make a real network request: returns the
   * fixture certificate set via $testCertPem regardless of $url, once the
   * real host-validation logic (which runs before this is called) has
   * already accepted or rejected the URL.
   */
  protected function fetchSigningCertPem(string $url): string {
    $this->certFetchAttempts++;
    return $this->testCertPem ?? '';
  }

}
