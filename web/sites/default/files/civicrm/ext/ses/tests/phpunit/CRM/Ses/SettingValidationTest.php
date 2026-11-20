<?php

use Civi\Api4\Setting;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Covers the validate callbacks on the ses_access_key / ses_secret_key
 * settings, and their wiring into the settings metadata.
 *
 * @group headless
 */
class CRM_Ses_SettingValidationTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * Shape-valid, entirely fictional: 40 characters from the base64 alphabet.
   */
  private const VALID_SECRET = 'wJalrXUtnFEMIzK7MDENGzbPxRfiCYEXAMPLEKEv';

  private const VALID_ACCESS_KEY = 'AKIAIOSFODNN7EXAMPLE';

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__ . '/..')
      ->apply();
  }

  /**
   * The metadata has to actually reference the callbacks, or none of the rest
   * of this matters - Setting.set is the path the admin form saves through.
   */
  public function testValidCredentialsAreStoredViaApi(): void {
    Setting::set(FALSE)
      ->addValue('ses_access_key', self::VALID_ACCESS_KEY)
      ->addValue('ses_secret_key', self::VALID_SECRET)
      ->execute();

    $this->assertSame(self::VALID_ACCESS_KEY, Civi::settings()->get('ses_access_key'));
    $this->assertSame(self::VALID_SECRET, Civi::settings()->get('ses_secret_key'));
  }

  /**
   * @dataProvider invalidSecretProvider
   */
  public function testInvalidSecretKeyIsRejectedViaApi(string $secret, string $expectedMessageFragment): void {
    Civi::settings()->set('ses_secret_key', self::VALID_SECRET);

    try {
      Setting::set(FALSE)->addValue('ses_secret_key', $secret)->execute();
      $this->fail('Setting.set should have rejected this secret key.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString($expectedMessageFragment, $e->getMessage());
      $this->assertStringNotContainsString($secret, $e->getMessage(), 'The message must not echo the secret back.');
    }

    $this->assertSame(self::VALID_SECRET, Civi::settings()->get('ses_secret_key'), 'A rejected value must not overwrite the stored one.');
  }

  public function invalidSecretProvider(): array {
    $real = self::VALID_SECRET;
    return [
      // "+" characters escaped as "&#43;".
      'html-escaped' => [str_replace('z', '&#43;', $real), 'HTML-escaped'],
      // "+" and "/" percent-encoded as "%2B" / "%2F".
      'percent-encoded' => [str_replace(['z', 'K'], ['%2B', '%2F'], $real), 'percent-encoded'],
      // 44 characters of base64 with a single "=" pad, as SES SMTP passwords are.
      'smtp password' => [substr($real, 0, 39) . 'abcd=', 'SMTP password'],
      'trailing newline' => [$real . "\n", 'whitespace'],
      'leading space' => [' ' . $real, 'whitespace'],
    ];
  }

  /**
   * AWS constrains neither the length nor the charset of a secret access key
   * (its IAM API model declares bounds for the access key ID only), so an
   * unfamiliar shape must not be rejected - being unable to save a working
   * credential is worse than a late InvalidSignatureException.
   *
   * @dataProvider unusualButAcceptedProvider
   */
  public function testUnusualCredentialShapesAreAccepted(string $setting, string $value): void {
    Setting::set(FALSE)->addValue($setting, $value)->execute();
    $this->assertSame($value, Civi::settings()->get($setting));
  }

  public function unusualButAcceptedProvider(): array {
    return [
      'secret one char short' => ['ses_secret_key', substr(self::VALID_SECRET, 0, 39)],
      'secret longer than 40' => ['ses_secret_key', self::VALID_SECRET . 'aa'],
      'secret with unexpected character' => ['ses_secret_key', substr(self::VALID_SECRET, 0, 39) . '-'],
      'access key longer than 20' => ['ses_access_key', 'AKIAIOSFODNN7EXAMPLEEXTRA'],
      'access key lowercase' => ['ses_access_key', 'akiaiosfodnn7example'],
      'access key with underscore' => ['ses_access_key', 'AKIA_IOSFODNN7EXAMPL'],
    ];
  }

  /**
   * Empty is how the setting ships, and clearing it must stay possible.
   */
  public function testEmptySecretKeyIsAllowed(): void {
    Civi::settings()->set('ses_secret_key', self::VALID_SECRET);

    Setting::set(FALSE)->addValue('ses_secret_key', '')->execute();
    $this->assertEmpty(Civi::settings()->get('ses_secret_key'));
  }

  /**
   * @dataProvider invalidAccessKeyProvider
   */
  public function testInvalidAccessKeyIsRejectedViaApi(string $accessKey, string $expectedMessageFragment): void {
    try {
      Setting::set(FALSE)->addValue('ses_access_key', $accessKey)->execute();
      $this->fail('Setting.set should have rejected this access key.');
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString($expectedMessageFragment, $e->getMessage());
      $this->assertStringNotContainsString($accessKey, $e->getMessage());
    }
  }

  public function invalidAccessKeyProvider(): array {
    return [
      'trailing newline' => [self::VALID_ACCESS_KEY . "\n", 'whitespace'],
      'leading space' => [' ' . self::VALID_ACCESS_KEY, 'whitespace'],
    ];
  }

}
