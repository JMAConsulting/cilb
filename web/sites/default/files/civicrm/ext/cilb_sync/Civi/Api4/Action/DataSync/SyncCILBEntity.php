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

/**
 * Migrate CILB Entity data
 * string getDateToSync
 * setDatetoSync(string $dateToSync)
 */
class SyncCILBEntity extends SyncFromSFTP {

  protected function retrieveCredentials() {
      // TODO: use/create settings
      $this->_sftpURL      = 'ventura.eastus.cloudapp.azure.com'; //'pearsonvue.com';
      $this->_sftpUser     = getenv('SFTP_VUE_USER');
      $this->_sftpPassword = getenv('SFTP_VUE_PASS');
      $this->_sftpHomeDir  = '/home/jma/entity-imports';
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

  /**
   * Scan folder for Entity files
   * Format: PTI_DBPR_ID_YYYY-MM-DD-##-##-##.csv
   */
  public function scanForFiles($date = NULL): array {

    $config = CRM_Core_Config::singleton();
    $dstdir = $config->customFileUploadDir . '/'.EU::ADV_IMPORT_FOLDER.'/test';

    CRM_Utils_File::createDir($dstdir);

    $files = scandir( $this->getPath('/') );
    $CSVFiles  = [];
    $formattedDate = date('Y-m-d', strtotime($this->dateToSync));

    // Download CSV
    foreach($files as $fileName) {
      if ( preg_match("/^PTI_DBPR_ID_".$formattedDate."[\n-]*.csv$/i", $fileName, $matches) ) {
        try {
          $this->downloadCSVFile($fileName, $dstdir);
          $CSVFiles[] = $fileName;
        } catch (Exception $ex) {
          throw new Exception("Could not download CSV file: $fileName.");
        }
      }
    }

    return $CSVFiles;
  }

  public function downloadCSVFile($fileName, $directory) {
    $stream = @fopen($this->getPath($fileName), 'r');
    if (! $stream) {
        throw new Exception("Could not open file: $fileName");
    }
    $contents = fread($stream, filesize($this->getPath($fileName)));
    file_put_contents ($directory . '/' . $fileName, $contents);
    @fclose($stream);
  }

}