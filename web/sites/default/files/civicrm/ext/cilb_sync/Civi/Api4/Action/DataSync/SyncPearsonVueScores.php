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

/**
 * Migrate PearsonVUE score data
 *
 */
class SyncPearsonVueScores extends SyncFromSFTP {

  protected function retrieveCredentials() {
      // TODO: define where to store these for easy switch between dev/staging/prod
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
    $dstdir = $config->customFileUploadDir . '/advimport';

    $files = scandir( $this->getPath('/') );

    foreach($files as $fileName) {
      if ( preg_match("/^FLELECONST[-_](NS|ABE)-".$this->dateToSync."a.zip$/i", $fileName, $matches) ) {

        $stream = @fopen($this->getPath($fileName), 'r');
        if (! $stream) {
            throw new Exception("Could not open file: $fileName");
        }
        $contents = fread($stream, filesize($this->getPath($fileName)));
        file_put_contents ($dstdir . '/' . $fileName, $contents);
        @fclose($stream);
      }
    }
  }

}