<?php

namespace Civi\Api4\Action\DataSync;

use CRM_Core_Config;

/**
 * Sync Data from SFTP sources
 *
 */
abstract class SyncFromSFTP extends \Civi\Api4\Generic\AbstractAction {

  protected $debug = TRUE;
  protected $checkPermissions = FALSE;
  protected $chain = [];

  /**
   * URL used to download the files via SFTP protocol 
   */
  protected string $_sftpURL = '';

  /**
   * SFTP Port 
   */
  protected string $_sftpPort = '22';

  /**
   * SFTP User 
   */
  protected string $_sftpUser = '';

  /**
   * SFTP Password 
   */
  protected string $_sftpPassword = '';

  /**
   * SFTP Home Directory 
   */
  protected string $_sftpHomeDir = '/';
  
  /**
   * SSH Connection
   */
  protected $_ssh;
  
  /**
   * SFTP Connection
   */
  protected $_ftp;

  /**
   * @var string
   *
   * Date to lookup
   */
  protected string $dateToSync = '';


  protected function closeConnection() {
    if ($this->_ssh) {
      @\ssh2_disconnect($this->_ssh);
    }
  }

  protected function prepareConnection() {
    
    // Credentials
    if (!$this->checkCredentials()) {
      $this->retrieveCredentials();
    }
    $this->checkCredentials(true);

    // Initial SSH connection
    $this->_ssh = @\ssh2_connect($this->_sftpURL, $this->_sftpPort);
    if (!$this->_ssh) {
      throw new \Civi\API\Exception\UnauthorizedException('Cannot connect to SFTP Host [SSH]');
    }
    if (!@\ssh2_auth_password($this->_ssh, $this->_sftpUser, $this->_sftpPassword)) {
      throw new \Civi\API\Exception\UnauthorizedException('Cannot connect to SFTP Host [Auth]');
    }

    // SFTP Connection
    $this->_ftp = @\ssh2_sftp($this->_ssh);
    if (!$this->_ftp) {
      throw new \Civi\API\Exception\UnauthorizedException('Cannot connect to SFTP Host [FTP]');
    }
  }

  /**
   * Retrieve credentials to connect via SFTP
   */
  protected function retrieveCredentials() {
    throw new \Civi\API\Exception\UnauthorizedException('Not implemented');
  }

  protected function checkCredentials($throwError = false): bool {
    if (empty($this->_sftpURL) || empty($this->_sftpUser) || empty($this->_sftpPassword)) {
      
      if ($throwError)
        throw new \Civi\API\Exception\UnauthorizedException('Missing credentials');
      
      return false;
    }

    return true;
  }

  protected function getPath( $filePathRelative = '/' ) {
    $folder = 'ssh2.sftp://' . $this->_ftp . $this->_sftpHomeDir;
    rtrim($folder, '/');

    if (substr($filePathRelative, 0) !== '/') {
      $filePathRelative = '/' . $filePathRelative;
    }
    
    return $folder . $filePathRelative;
  }


}
