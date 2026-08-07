<?php

use Aws\SesV2\SesV2Client;
use Aws\Credentials\Credentials;

/**
 * Singleton class for using a single instance of
 * Aws\SesV2\SesV2Client
 *
 * Using a single persistent instance improves performance
 * as the client is not recreated for each email sent.
 */
class CRM_Ses_SesClient {

  /**
   * The SES Client instance is stored in this
   * static private variable
   *
   * @var \Aws\SesV2\SesV2Client|null
   */
  private static ?SesV2Client $instance;

  private function __construct() {}

  private function __clone() {}

  public static function getInstance(): SesV2Client {
    if (!isset(self::$instance)) {
      $ses_access_key = Civi::settings()->get('ses_access_key');
      $ses_secret_key = Civi::settings()->get('ses_secret_key');
      $ses_region = Civi::settings()->get('ses_region');
      if (empty($ses_access_key) || empty($ses_secret_key) || empty($ses_region)) {
        throw new Exception("Missing required Amazon SES configuration. Please configure the extention in Administer > CiviMail > SES settings.");
      }

      $credentials = new Credentials($ses_access_key, $ses_secret_key);
      self::$instance = new SesV2Client([
        'version' => 'latest',
        'region' => $ses_region,
        'credentials' => $credentials,
      ]);
    }
    return self::$instance;
  }

  /**
   * Test seam: lets tests substitute a client wired to Aws\MockHandler (or
   * reset to NULL to force getInstance() to rebuild from settings on next
   * call), instead of a real SES connection.
   *
   * @param \Aws\SesV2\SesV2Client|null $client
   */
  public static function setInstance(?SesV2Client $client): void {
    self::$instance = $client;
  }

}
