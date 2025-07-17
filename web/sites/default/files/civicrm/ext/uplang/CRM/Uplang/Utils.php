<?php

use CRM_Uplang_ExtensionUtil as E;

class CRM_Uplang_Utils {

  /**
   * Returns the directory where we can download l10n files.
   *
   * Checks that the l10n directory is configured, exists, and is writable
   * We do not use CRM_Core_I18n::getResourceDir() because that only returns
   * the path under [civicrm.private] if the directory exists. We will mkdir
   * if necessary.
   */
  public static function getDownloadDir() {
    $l10n = CRM_Core_I18n::getResourceDir();

    if (!is_writable($l10n)) {
      $l10n = \Civi::paths()->getPath('[civicrm.private]/l10n/.');
    }

    if (empty($l10n)) {
      throw new Exception(E::ts('Your localization directory is not configured.'));
    }

    if (!is_dir($l10n) && !@mkdir($l10n, 0775, TRUE)) {
      throw new Exception(E::ts('Your localization directory, %1, does not exist and could not be created.', [1 => $l10n]));
    }

    if (!is_writable($l10n)) {
      throw new Exception(E::ts('Your localization directory, %1, is not writable.', [1 => $l10n]));
    }

    return $l10n;
  }

  public static function getNonUSLocale() {
    $locales = CRM_Core_I18n::uiLanguages(TRUE);

    foreach ($locales as $locale) {
      if ($locale != 'en_US') {
        return $locale;
      }
    }

    throw new Exception(E::ts('Only en_US was found. Nothing to update.'));
  }

  public static function getLastUpdateTime() {
    $l10n = CRM_Uplang_Utils::getDownloadDir();
    $locale = self::getNonUSLocale();
    $localFile = "$l10n/$locale/LC_MESSAGES/civicrm.mo";
    $mtime = filemtime($localFile);
    return $mtime;
  }

  /**
   * Fetches updated localization files from civicrm.org
   * it refreshes core and all extension localization files
   * for all needed languages (ie. default language in singlelingual or all enabled in multilingual)
   *
   * @param string $locales Comma-delimited list of languages to be fetched (defaults to all enabled languages)
   * @param bool $forceDownload If true, re-download even if we already downloaded within the last day
   *
   * @return array|void
   * @throws \Exception
   */
  public static function updateAllFiles($locales = '') {
    $l10n = CRM_Uplang_Utils::getDownloadDir();

    // Get the list of locales we need to download
    // If no locale was specified (ex: api params), we update all enabled locales
    $locales = ($locales ? explode(',', $locales) : []);

    if (empty($locales)) {
      $locales = CRM_Core_I18n::uiLanguages(TRUE);
    }

    // Download the l10n files from civicrm.org
    $downloaded = [];

    foreach ($locales as $locale) {
      if ($locale == 'en_US') {
        continue;
      }

      // Sanity tests - does the locale look legit?
      if (!preg_match('/^\w\w_\w\w$/', $locale)) {
        throw new Exception(E::ts('Unsupported language format: %1', [1 => $locale]));
      }

      try {
        // Download core translation files
        $remoteURL = "https://download.civicrm.org/civicrm-l10n-core/mo/$locale/civicrm.mo";
        $localFile = "$l10n/$locale/LC_MESSAGES/civicrm.mo";
        if (CRM_Uplang_Utils::downloadFile($remoteURL, $localFile)) {
          $downloaded['core']++;
        }

        // Download extensions translation files
        foreach (CRM_Core_PseudoConstant::getModuleExtensions() as $module) {
          $extname = $module['prefix'];
          $remoteURL = "https://download.civicrm.org/civicrm-l10n-extensions/mo/$extname/$locale/$extname.mo";
          $localFile = "$l10n/$locale/LC_MESSAGES/$extname.mo";
          if (CRM_Uplang_Utils::downloadFile($remoteURL, $localFile)) {
            if (!isset($downloaded[$extname])) {
              $downloaded[$extname] = 0;
            }
            $downloaded[$extname]++;
          }
        }
      }
      catch (GuzzleHttp\Exception\ConnectException $e) {
        throw new Exception(E::ts('Update Language: failed to update: ConnectException on %1. Error: %2', [1 => $remoteURL, 2 => $e->getMessage()]));
      }
      catch (Exception $e) {
        throw new Exception(E::ts('Update Language: failed to update: %1', [1 => $e->getMessage()]));
      }
    }

    return $downloaded;
  }

  /**
   * Downloads a particular localization files from civicrm.org
   * will check that we have not already downloaded it recently.
   *
   * @param string $remoteURL URL for this particular file
   * @param string $localFile where to store this file locally
   *
   * @return boolean true if the file was refreshed
   * @throws \Exception
   */
  public static function downloadFile($remoteURL, $localFile) : bool {
    $localeDir = dirname($localFile);
    // uplang_fetch() checks the "l10n" directory, but not "l10n/fr_FR/LC_MESSAGES", for example
    if (!is_dir($localeDir) && !@mkdir($localeDir, 0775, TRUE)) {
      throw new Exception(E::ts('The localization directory, %1, does not exist and could not be created.', [1 => $localeDir]));
    }

    $client = new GuzzleHttp\Client();

    try {
      $response = $client->request('GET', $remoteURL, ['sink' => $localFile, 'timeout' => 5]);
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      // issue#12 sink will sometimes save a text file with the error
      unlink($localFile);
      // Log an appropriate error that might help debugging
      if ($e->hasResponse()) {
        if ($e->getResponse()->getStatusCode() == 404) {
          // 404 is normal for non-reviewed extensions
          \Civi::log()->info('Update Language: Guzzle error ' . $e->getResponse()->getStatusCode() . ': ' . $e->getResponse()->getReasonPhrase() . ' on URL: ' . $remoteURL);
        }
        else {
          \Civi::log()->error('Update Language: Guzzle error ' . $e->getResponse()->getStatusCode() . ': ' . $e->getResponse()->getReasonPhrase() . ' on URL: ' . $remoteURL);
        }
      }
      else {
        \Civi::log()->error('Update Language: Guzzle unknown error: ' . $e->getMessage() . ' on URL: ' . $remoteURL);
      }
      return FALSE;
    }

    if ($response->getStatusCode() !== 200) {
      \Civi::log()->error('Update Language: Guzzle unknown error: ' . $e->getStatusCode() . ' on URL: ' . $remoteURL);
      return FALSE;
    }

    if (!file_exists($localFile)) {
      \Civi::log()->error('Update Language: download was succesful but the local file was not found: ' . $localFile);
      return FALSE;
    }

    return TRUE;
  }

}
