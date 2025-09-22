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

  protected string $sftpURL = 'sftp2.pearsonvue.com';


  public function _run(Result $result) {

    $this->prepareConnection(); // throws error if fails

    $sftp = ssh2_sftp($this->conn);
    echo "<pre>sftp -> " . print_r($sftp, true) . "</pre>";
  }

}