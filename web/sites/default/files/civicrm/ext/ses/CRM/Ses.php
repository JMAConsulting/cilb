<?php
/**
 * This extension allows CiviCRM to send emails and process bounces through
 * the Ses service.
 *
 */

use CRM_Ses_ExtensionUtil as E;

class CRM_Ses {

  /**
   * Creates the special Mailing for tracking transactional emails
   *
   * @return Boolean
   *   True if the mailing was created.
   */
  public static function createTransactionalMailing() {
    $result = civicrm_api3('Mailing', 'get', [
      'name' => 'Ses Transactional Emails',
    ]);
    $existingMailing = \Civi\Api4\Mailing::get(FALSE)
      ->addWhere('name', '=', 'Ses Transactional Emails')
      ->execute()
      ->first();

    if (empty($existingMailing)) {
      // Create entry in civicrm_mailing
      $mailingParams = [
        'subject' => E::ts('Ses Transactional Emails (do not delete)'),
        'name' => 'Ses Transactional Emails',
        'url_tracking' => TRUE,
        'forward_replies' => FALSE,
        'auto_responder' => FALSE,
        'open_tracking' => TRUE,
        'is_completed' => FALSE,
      ];

      $mailing = CRM_Mailing_BAO_Mailing::add($mailingParams);

      // Add entry in civicrm_mailing_job
      $saveJob = new CRM_Mailing_DAO_MailingJob();
      $saveJob->start_date = $saveJob->end_date = date('YmdHis');
      $saveJob->status = 'Complete';
      $saveJob->job_type = "Special: All Ses transactional emails";
      $saveJob->mailing_id = $mailing->id;
      $saveJob->save();

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Validate callback for the ses_access_key setting.
   *
   * @param mixed $value
   *
   * @return bool
   * @throws \CRM_Core_Exception
   */
  public static function validateAccessKey($value): bool {
    if ($value === NULL || $value === '') {
      return TRUE;
    }
    if (!is_string($value)) {
      throw new CRM_Core_Exception(E::ts('SES Access Key must be a string.'));
    }
    if (trim($value) !== $value) {
      throw new CRM_Core_Exception(E::ts('SES Access Key has leading or trailing whitespace. Re-paste it without the surrounding space or newline.'));
    }
    return TRUE;
  }

  /**
   * Validate callback for the ses_secret_key setting.
   *
   * A broken secret is only reported by AWS at send time, as an
   * InvalidSignatureException that says nothing about which credential is
   * wrong, so catch the corruptions we can positively identify at the point
   * the value is entered instead.
   *
   * @param mixed $value
   *
   * @return bool
   * @throws \CRM_Core_Exception
   */
  public static function validateSecretKey($value): bool {
    if ($value === NULL || $value === '') {
      return TRUE;
    }
    if (!is_string($value)) {
      throw new CRM_Core_Exception(E::ts('SES Secret Key must be a string.'));
    }
    if (trim($value) !== $value) {
      throw new CRM_Core_Exception(E::ts('SES Secret Key has leading or trailing whitespace. Re-paste it without the surrounding space or newline.'));
    }
    $problem = self::describeInvalidSecretKey($value);
    if ($problem !== NULL) {
      throw new CRM_Core_Exception($problem);
    }
    return TRUE;
  }

  /**
   * Names a specific, demonstrable problem with a secret key, or NULL if
   * there is nothing identifiably wrong with it.
   *
   * Deliberately not a check that the value *is* a valid secret: AWS declares
   * no length or charset for one (the IAM API model constrains only the access
   * key ID, to 16-128 word characters), so the familiar 40 base64 characters
   * is what IAM happens to issue rather than a rule to enforce. Rejecting an
   * unfamiliar shape would lock a site out of saving a working credential.
   *
   * Never include the value itself: these messages reach the settings form and
   * the log.
   */
  private static function describeInvalidSecretKey(string $value): ?string {
    // An SES SMTP password is base64 of a version byte plus a 32-byte HMAC - 33
    // bytes, so 44 characters ending in a single "=" pad. Its username is an
    // access key ID, so an SMTP credential pair looks plausible in these two
    // fields but cannot sign an API request.
    if (preg_match('#^[A-Za-z0-9+/]{43}=$#', $value)) {
      return E::ts('This looks like an SES SMTP password rather than an IAM secret access key. The SES API needs the secret access key from IAM; SMTP credentials only work over SMTP.');
    }
    // A credential never contains "&...;" or "%XX", so either decode altering
    // the value means something escaped it in transit.
    if (html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') !== $value) {
      return E::ts('SES Secret Key contains HTML-escaped characters (such as "&#43;" in place of "+"). Re-paste it directly from AWS.');
    }
    // rawurldecode, not urldecode: the latter also turns "+" into a space,
    // and "+" is a legitimate secret key character.
    if (rawurldecode($value) !== $value) {
      return E::ts('SES Secret Key looks percent-encoded (an escape sequence beginning with "%" in place of "+" or "/"). Re-paste it directly from AWS.');
    }
    return NULL;
  }

}
