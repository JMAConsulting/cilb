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

use Civi\Api4\Generic\Result;
use CRM_Core_Config;
use CRM_Core_DAO;
use Exception;
use ZipArchive;

/**
 * Migrate PearsonVUE score data
 * string getDateToSync
 * setDatetoSync(string $dateToSync)
 */
class SyncPearsonVueEntity extends SyncFromSFTP {

  protected function retrieveCredentials() {
      // TODO: define where to store these for easy switch between dev/staging/prod
      // TODO CHANGE FOR correct production palce for entity files
      $this->_sftpURL      = 'ventura.eastus.cloudapp.azure.com'; //'pearsonvue.com';
      $this->_sftpUser     = getenv('SFTP_VUE_USER');
      $this->_sftpPassword = getenv('SFTP_VUE_PASS');
      $this->_sftpHomeDir  = '/home/jma/pearson-imports';
  }


  public function _run(Result $result) {

    $this->prepareConnection(); // throws error if fails

    $this->dateToSync = date('Ymd'); // TODO: use param
    $this->scanForFiles();

    $this->closeConnection();

  }

  public function scanForFiles($date = NULL) {

    $config = CRM_Core_Config::singleton();
    $dstdir = $config->customFileUploadDir . '/advimport/test';

    CRM_Utils_File::createDir($dstdir);

    $files = scandir( $this->getPath('/') );
    $formattedDate = date('Y-m-d', strtotime($this->dateToSync));

    // Download CSV
    foreach($files as $fileName) {
      if ( preg_match("/^PTI_DBPR_ID-".$formattedDate.".csv$/i", $fileName, $matches) ) {
        try {
          $this->downloadCSVFile($fileName, $dstdir);
          $zipFiles[$matches[1]] = $fileName;
        } catch (Exception $ex) {
          throw new Exception("Could not download CSV file: $fileName.");
        }
      }
    }
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