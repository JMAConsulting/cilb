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
      // TODO: use/create settings
      $this->_sftpURL      = 'ventura.eastus.cloudapp.azure.com'; //'pearsonvue.com';
      $this->_sftpUser     = getenv('SFTP_VUE_USER');
      $this->_sftpPassword = getenv('SFTP_VUE_PASS');
      $this->_sftpHomeDir  = '/home/jma/pearson-imports';
  }


  public function _run(Result $result) {

    $this->prepareConnection(); // throws error if fails
    
    // Get date from param or now()
    $realDate = EU::getTimestampDate($this->dateToSync);
    $this->dateToSync = date('Ymd', $realDate);

    $downloadedFiles = $this->scanForFiles();

    $this->closeConnection();

    $result['files'] = $downloadedFiles;

    return $result;

  }

  public function scanForFiles($date = NULL) {

    $config = CRM_Core_Config::singleton();
    $dstdir = $config->customFileUploadDir . '/'.EU::ADV_IMPORT_FOLDER.'/test';

    CRM_Utils_File::createDir($dstdir);

    $files = scandir( $this->getPath('/') );
    $zipFiles = ['ABE' => '', 'NS' => ''];
    $datFiles = ['ABE' => '', 'NS' => ''];
    $formattedDate = date('Ymd', strtotime($this->dateToSync));

    // Download ZIP
    foreach($files as $fileName) {
      if ( preg_match("/^FLELECONST[-_](NS|ABE)-".$formattedDate."a.zip$/i", $fileName, $matches) ) {
        try {
          $this->downloadZIPFile($fileName, $dstdir);
          $zipFiles[$matches[1]] = $fileName;
        } catch (Exception $ex) {
          throw new Exception("Could not download ZIP file: $fileName.");
        }
      }
    }

    // Extract DAT and cleanup
    foreach($zipFiles as $type => $fileName) {
      $datFiles[$type] = $this->extractExamDATFile($type, $dstdir . '/' . $fileName, $dstdir . '/' . $formattedDate);
    }

    return $datFiles;
  }

  public function downloadZIPFile($fileName, $directory) {
    $stream = @fopen($this->getPath($fileName), 'r');
    if (! $stream) {
        throw new Exception("Could not open file: $fileName");
    }
    $contents = fread($stream, filesize($this->getPath($fileName)));
    file_put_contents ($directory . '/' . $fileName, $contents);
    @fclose($stream);
  }

  public function extractExamDATFile($type, $zipFile, $directory): array {
    $formattedDate = date('Y-m-d', strtotime($this->dateToSync));
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
        unlink($zipFile);
        throw new Exception("Could not extract files.");
    }

    // Clean up
    unlink($zipFile);

    return $filesToExtract;
  }

}