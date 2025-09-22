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
use CRM_Core_DAO;
use Exception;

/**
 * Migrate Contact data
 *
 */
class SyncPearsonVueScores extends SyncFromSFTP {

  protected function retrieveCredentials() {
      $this->sftpURL = 'ventura.eastus.cloudapp.azure.com';//'pearsonvue.com';
      $this->sftpUser = getenv('SFTP_VUE_USER');
      $this->sftpPassword = getenv('SFTP_VUE_PASS');
  }


  public function _run(Result $result) {

    $this->prepareConnection(); // throws error if fails
    
    $sftp = @\ssh2_sftp($this->conn);
    if (!$sftp) {
      throw new \Civi\API\Exception\UnauthorizedException('Cannot connect to SFTP Host [FTP]');
    }

    $this->closeConnection();

  }

}