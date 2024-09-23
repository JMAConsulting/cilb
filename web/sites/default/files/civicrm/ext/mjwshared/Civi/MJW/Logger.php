<?php

namespace Civi\MJW;


/**
 * Abstracts the Civi::log class so that we:
 *   - Don't have to specify channel each time we log.
 *   - Automatically log the entityID/name if provided.
 */
class Logger {

  /**
   * The Log channel
   *
   * @var string
   */
  private string $logChannel = '';

  /**
   * The entityID/Name
   *
   * @var string
   */
  private string $logEntity = '';

  public function __construct(string $logChannel, string $logEntity) {
    $this->logChannel = $logChannel;
    $this->logEntity = $logEntity;
  }

  /**
   * Log an info message with payment processor prefix
   * @param string $message
   *
   * @return void
   */
  public function logInfo(string $message) {
    $this->log('info', $message);
  }

  /**
   * Log an error message with payment processor prefix
   *
   * @param string $message
   *
   * @return void
   */
  public function logError(string $message) {
    $this->log('error', $message);
  }

  /**
   * Log a debug message with payment processor prefix
   *
   * @param string $message
   *
   * @return void
   */
  public function logDebug(string $message) {
    $this->log('debug', $message);
  }

  /**
   * @param string $level
   * @param string $message
   *
   * @return void
   */
  private function log(string $level, string $message) {
    $channel = $this->logChannel;
    $prefix = $channel . '(' . $this->logEntity . '): ';
    \Civi::log($channel)->$level($prefix . $message);
  }
}