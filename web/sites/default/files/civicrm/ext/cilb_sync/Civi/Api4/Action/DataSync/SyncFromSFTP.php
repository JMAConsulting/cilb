<?php

namespace Civi\Api4\Action\DataSync;

/**
 * Sync Data from SFTP sources
 *
 */
abstract class SyncFromSFTP extends \Civi\Api4\Generic\AbstractAction {

  /**
   * We can override the default value of params, or add docblocks to them,
   * by redeclaring them in our action override.
   *
   * For the parent's docblock contents to appear in the _API Explorer_ as well,
   * we add the `@inheritDoc` annotation and get this:
   * @inheritDoc
   */
  protected $debug = TRUE;

  protected $checkPermissions = FALSE;

  /**
   * @var string
   *
   * URL used to download the files via SFTP protocol 
   */
  protected string $sftpURL = '';

  /**
   * @var string
   *
   * SFTP Port 
   */
  protected string $sftpPort = '22';

  /**
   * @var string
   *
   * SFTP User 
   */
  protected string $sftpUser = '';

  /**
   * @var string
   *
   * SFTP Password 
   */
  protected string $sftpPassword = '';
  
  /**
   * 
   */
  protected $conn;

  /**
   * @var string
   *
   * Date to lookup
   */
  protected string $dateToSync = '';


  protected function closeConnection() {
    if ($this->conn) {
      @\ssh2_disconnect($this->conn);
    }
  }

  protected function prepareConnection() {
    
    // Credentials
    if (!$this->checkCredentials()) {
      $this->retrieveCredentials();
    }
    $this->checkCredentials(true);

    // Initial connection
    $this->conn = @\ssh2_connect($this->sftpURL, $this->sftpPort);
    if (!$this->conn) {
      throw new \Civi\API\Exception\UnauthorizedException('Cannot connect to SFTP Host [SSH]');
    }
    if (!@\ssh2_auth_password($this->conn, $this->sftpUser, $this->sftpPassword)) {
      throw new \Civi\API\Exception\UnauthorizedException('Cannot connect to SFTP Host [Auth]');
    }
  }

  protected function retrieveCredentials() {
    throw new \Civi\API\Exception\UnauthorizedException('Not implemented');
  }

  protected function checkCredentials($throwError = false): bool {
    if (empty($this->sftpURL) || empty($this->sftpUser) || empty($this->sftpPassword)) {
      
      if ($throwError)
        throw new \Civi\API\Exception\UnauthorizedException('Missing credentials');
      
      return false;
    }

    return true;
  }

}
