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
   * 
   */
  protected $conn;

  /**
   * @var string
   *
   * Date to lookup
   */
  protected string $dateToSync = '';


  protected function prepareConnection() {
    //$sftpURL;
    if ( empty($this->sftpURL) ) {
      throw new \Civi\API\Exception\UnauthorizedException('Missing SFTP connection details.');
    }
    
    $this->conn = @\ssh2_connect($this->sftpURL, $this->sftpPort);
    if (!$this->conn) {
      throw new \Civi\API\Exception\UnauthorizedException('Cannot connect to SFTP Host');
    }
    \@ssh2_auth_password($this->conn, 'username', 'password');
  }

}
