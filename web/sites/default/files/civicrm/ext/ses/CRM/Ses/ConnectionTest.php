<?php

use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use CRM_Ses_ExtensionUtil as E;

/**
 * Probes an SES account with the configured credentials, so that a broken
 * configuration is reported when it is saved rather than at send time.
 */
class CRM_Ses_ConnectionTest {

  const CREDENTIALS_OK = 'ok';
  const CREDENTIALS_REJECTED = 'rejected';
  const CREDENTIALS_ERROR = 'error';

  const PERMISSION_GRANTED = 'granted';
  const PERMISSION_DENIED = 'denied';
  const PERMISSION_SKIPPED = 'skipped';
  const PERMISSION_UNKNOWN = 'unknown';

  /**
   * AWS error codes meaning the request signature itself was rejected, ie. the
   * credentials are wrong.
   *
   * AccessDeniedException is deliberately absent: it means the signature
   * verified and only the IAM policy was lacking, which tells us the
   * credentials are good. That is what lets these probes use API actions the
   * site's policy may not grant.
   */
  private const SIGNATURE_ERROR_CODES = [
    'InvalidSignatureException',
    'SignatureDoesNotMatch',
    'InvalidClientTokenId',
    'UnrecognizedClientException',
    'ExpiredTokenException',
    'AuthFailure',
  ];

  /**
   * Probe the account.
   *
   * @param \Aws\SesV2\SesV2Client $client
   * @param bool $testSuppressionPermission
   *
   * @return array
   *   Keys: credentials, credentialsDetail, suppressionPermission, suppressionDetail.
   */
  public static function run(SesV2Client $client, bool $testSuppressionPermission): array {
    $result = [
      'credentials' => self::CREDENTIALS_OK,
      'credentialsDetail' => NULL,
      'suppressionPermission' => self::PERMISSION_SKIPPED,
      'suppressionDetail' => NULL,
    ];

    try {
      $client->getAccount();
    }
    catch (AwsException $e) {
      $code = (string) $e->getAwsErrorCode();
      if ($code === 'AccessDeniedException') {
        // Signature verified, ses:GetAccount simply is not granted - which this
        // extension never needs.
      }
      elseif (in_array($code, self::SIGNATURE_ERROR_CODES, TRUE)) {
        $result['credentials'] = self::CREDENTIALS_REJECTED;
        $result['credentialsDetail'] = $code;
        return $result;
      }
      else {
        $result['credentials'] = self::CREDENTIALS_ERROR;
        $result['credentialsDetail'] = $code . ': ' . $e->getAwsErrorMessage();
        return $result;
      }
    }
    catch (\Throwable $e) {
      $result['credentials'] = self::CREDENTIALS_ERROR;
      $result['credentialsDetail'] = $e->getMessage();
      return $result;
    }

    if ($testSuppressionPermission) {
      $result['suppressionPermission'] = self::testSuppressionPermission($client, $result['suppressionDetail']);
    }

    return $result;
  }

  /**
   * Whether the policy allows ses:DeleteSuppressedDestination.
   *
   * Asks SES to remove an address that is not on the suppression list: a
   * permitted call answers NotFoundException, a forbidden one
   * AccessDeniedException, and nothing is deleted either way. The address is
   * random and at an IANA-reserved domain that cannot receive mail, so it will
   * not be suppressed - and were it somehow listed, removing it is harmless.
   *
   * Worth probing specifically because CRM_Ses_SuppressionList::maybeRemove()
   * logs and swallows failures, so a missing permission is otherwise invisible:
   * CiviCRM takes the contact off hold while SES keeps dropping their mail.
   */
  private static function testSuppressionPermission(SesV2Client $client, ?string &$detail): string {
    $probeAddress = 'civicrm-ses-permission-probe-' . bin2hex(random_bytes(8)) . '@example.org';

    try {
      $client->deleteSuppressedDestination(['EmailAddress' => $probeAddress]);
      // Succeeding means the address happened to be listed. Either way the call
      // was authorised, which is all this is asking.
      return self::PERMISSION_GRANTED;
    }
    catch (AwsException $e) {
      $code = (string) $e->getAwsErrorCode();
      if ($code === 'NotFoundException') {
        return self::PERMISSION_GRANTED;
      }
      if ($code === 'AccessDeniedException') {
        return self::PERMISSION_DENIED;
      }
      $detail = $code . ': ' . $e->getAwsErrorMessage();
      return self::PERMISSION_UNKNOWN;
    }
    catch (\Throwable $e) {
      $detail = $e->getMessage();
      return self::PERMISSION_UNKNOWN;
    }
  }

  /**
   * Probe the configured account and report the outcome as session status
   * messages. Never throws: a failed probe must not stop the settings saving.
   *
   * @param \Aws\SesV2\SesV2Client|null $client
   *   Test seam. Left NULL in production, where the client is built from the
   *   settings that were just saved.
   */
  public static function reportToSession(?SesV2Client $client = NULL): void {
    if ($client === NULL) {
      // The client is a per-request singleton, so drop any instance built
      // before the settings were saved.
      CRM_Ses_SesClient::setInstance(NULL);
      try {
        $client = CRM_Ses_SesClient::getInstance();
      }
      catch (\Throwable $e) {
        // Incomplete configuration - the settings form is where you go to fix
        // that, so saying so here would be noise.
        return;
      }
    }

    $result = self::run($client, (bool) Civi::settings()->get('ses_suppression_list_removal'));

    foreach (self::describe($result) as [$message, $title, $severity]) {
      CRM_Core_Session::setStatus($message, $title, $severity);
    }
  }

  /**
   * Turn a probe result into status messages.
   *
   * @return array[]
   *   Each entry is [message, title, severity].
   */
  public static function describe(array $result): array {
    $messages = [];

    switch ($result['credentials']) {
      case self::CREDENTIALS_REJECTED:
        $messages[] = [
          E::ts('Amazon SES rejected these credentials (%1). No email will send until they are corrected. Check that the secret is the IAM secret access key, not an SES SMTP password.', [
            1 => $result['credentialsDetail'],
          ]),
          E::ts('SES credentials rejected'),
          'error',
        ];
        return $messages;

      case self::CREDENTIALS_ERROR:
        $messages[] = [
          E::ts('Could not reach Amazon SES to check these credentials: %1', [1 => $result['credentialsDetail']]),
          E::ts('SES not reachable'),
          'warning',
        ];
        return $messages;
    }

    $messages[] = [
      // Nothing short of sending an email tests ses:SendEmail, so do not imply
      // that it has been.
      E::ts('Amazon SES accepted these credentials. Permission to send cannot be verified without sending an email.'),
      E::ts('SES credentials verified'),
      'success',
    ];

    if ($result['suppressionPermission'] === self::PERMISSION_DENIED) {
      $messages[] = [
        E::ts('Suppression list removal is enabled, but this IAM policy does not allow ses:DeleteSuppressedDestination. Taking a contact off hold will leave them suppressed at Amazon SES and their mail will still be dropped, without any error. Add the permission or turn the option off.'),
        E::ts('SES permission missing'),
        'warning',
      ];
    }
    elseif ($result['suppressionPermission'] === self::PERMISSION_UNKNOWN) {
      $messages[] = [
        E::ts('Could not determine whether this IAM policy allows ses:DeleteSuppressedDestination: %1', [
          1 => $result['suppressionDetail'],
        ]),
        E::ts('SES permission unknown'),
        'warning',
      ];
    }

    return $messages;
  }

}
