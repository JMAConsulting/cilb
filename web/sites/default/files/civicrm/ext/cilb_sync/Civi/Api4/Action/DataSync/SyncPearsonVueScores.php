<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

namespace Civi\Api4\Action\DataSync;

use CRM_CILB_Sync_Utils as EU;

use Civi\Api4\Generic\Result;
use CRM_Core_Config;
use CRM_Utils_File;
use Exception;
use ZipArchive;

/**
 * Migrate PearsonVUE score data
 *
 */
class SyncPearsonVueScores extends SyncFromSFTP {

  protected function retrieveCredentials() {
      $this->_sftpURL      = \Civi::settings()->get('sftp_pearson_url');
      $this->_sftpPort     = \Civi::settings()->get('sftp_pearson_url_port') ?? '22';
      $this->_sftpUser     = \Civi::settings()->get('sftp_pearson_user');
      $encryptedPassword   = \Civi::settings()->get('sftp_pearson_password');
      $this->_sftpPassword = \Civi::service('crypto.token')->decrypt($encryptedPassword, ['plain', 'CRED']);
      $this->_sftpHomeDir  = \Civi::settings()->get('sftp_pearson_home_dir');
  }


  public function _run(Result $result) {

    $this->prepareConnection(); // throws error if fails

    // Get date from param or now()
    $realDate = EU::getTimestampDate($this->dateToSync);
    $this->dateToSync = date('Ymd', $realDate);

    $downloadedFiles = $this->scanForFiles();

    $this->closeConnection();

    $result['date']   = $this->dateToSync;
    $result['files']  = $downloadedFiles;

    return $result;

  }

  public function scanForFiles($date = NULL) {

    $dstdir = EU::getDestinationDir();

    CRM_Utils_File::createDir($dstdir);

    $files = scandir( $this->getPath('/') );
    $zipFiles = ['ABE' => '', 'NS' => ''];
    $datFiles = ['ABE' => '', 'NS' => ''];
    $formattedDate = date(\Civi::settings()->get('sftp_pearson_zip_date_file_name_format'), strtotime($this->dateToSync));

    // Download ZIP
    foreach($files as $fileName) {
      if ( preg_match("/^FLELECONST[-_](NS|ABE)-".$formattedDate."a.zip$/i", $fileName, $matches) ) {
        try {
          if ( $this->downloadZIPFile($fileName, $dstdir) ) {
            $zipFiles[$matches[1]] = $fileName;
          } else {
            throw new Exception("Could not download ZIP file: $fileName.");
          }
        } catch (Exception $ex) {
          throw new Exception("Could not download ZIP file: $fileName.");
        }
      }
    }

    // Extract DAT and cleanup
    foreach($zipFiles as $type => $fileName) {
      if (!empty($fileName)) {
        $datFiles[$type] = $this->extractExamDATFile($type, $dstdir . '/' . $fileName, $dstdir);
      }
    }

    return $datFiles;
  }

  public function downloadZIPFile($fileName, $directory): bool {
    $stream = @fopen($this->getPath($fileName), 'rb');
    if (! $stream) {
        throw new Exception("Could not open file: $fileName");
    }

    $local = fopen($directory . '/' . $fileName, 'w');

    // Write buffer
    // Needed for files larger than 8k
    while(!feof($stream)){
        fwrite($local, fread($stream, 8192));
    }

    @fclose($local);
    @fclose($stream);

    $bytes = filesize($directory . '/' . $fileName );

    return ($bytes !== false && $bytes > 0);
  }

  public function extractExamDATFile($type, $zipFile, $directory): array {
    $formattedDate = date(\Civi::settings()->get('sftp_pearson_dat_date_file_name_format'), strtotime($this->dateToSync));
    $filesToExtract = ($type == "ABE") ?
      ['examABE-'.$formattedDate.'-a.dat'] :
        ['exam-'.$formattedDate.'a-ns.dat'];

    // Extract
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        if (!file_exists($directory)) {
          mkdir($directory, 0777, true);
        }
        $zip->extractTo($directory, $filesToExtract);
        $zip->close();
    } else {
        $fileName = basename($zipFile);
        unlink($zipFile);
        throw new Exception("Could not extract files for $fileName.");
    }

    // Clean up
    unlink($zipFile);

    return $filesToExtract;
  }

}