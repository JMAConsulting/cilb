<?php

namespace Drupal\civicrm_group_roles\Batch;

use Drupal\civicrm_group_roles\CivicrmGroupRoles;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Sync the roles to civicrm groups.
 */
class Sync {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The String Translation Service.
   *
   * @var \Drupal\Core\StringTranslation\StringTranslationTrait
   */
  protected $stringTranslation;

  /**
   * The Messenger Service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * CiviCRM Group Role Service.
   *
   * @var \Drupal\civicrm_group_roles\CivicrmGroupRoles
   */
  protected $civicrmGroupRoles;

  /**
   * Database Service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Sync constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Messenger Service.
   * @param \Drupal\civicrm_group_roles\CivicrmGroupRoles $civicrmGroupRoles
   *   The Messenger Service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database Service.
   */
  public function __construct(
    TranslationInterface $stringTranslation,
    MessengerInterface $messenger,
    CivicrmGroupRoles $civicrmGroupRoles,
    Connection $database
  ) {
    $this->stringTranslation = $stringTranslation;
    $this->messenger = $messenger;
    $this->civicrmGroupRoles = $civicrmGroupRoles;
    $this->database = $database;
  }

  /**
   * Get the batch.
   *
   * @return array
   *   A batch API array for syncing user groups and roles.
   */
  public function getBatch() {
    $batch = [
      'title' => $this->t('Updating Users...'),
      'operations' => [],
      'init_message' => $this->t('Starting Update'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('An error occurred during processing'),
      'finished' => [$this, 'finished'],
    ];

    $batch['operations'][] = [[$this, 'process'], []];

    return $batch;
  }

  /**
   * Batch API process callback.
   *
   * @param mixed $context
   *   Batch API context data.
   */
  public function process(&$context) {
    $civicrmGroupRoles = $this->civicrmGroupRoles;

    if (!isset($context['sandbox']['cids'])) {
      $context['sandbox']['cids'] = $civicrmGroupRoles->getSyncContactIds();
      $context['sandbox']['max'] = count($context['sandbox']['cids']);
      $context['results']['processed'] = 0;
    }

    $cid = array_shift($context['sandbox']['cids']);
    if ($account = $civicrmGroupRoles->getContactAccount($cid)) {
      $civicrmGroupRoles->syncContact($cid, $account);
    }
    $context['results']['processed']++;

    if (count($context['sandbox']['cids']) > 0) {
      $context['finished'] = 1 - (count($context['sandbox']['cids']) / $context['sandbox']['max']);
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Batch API success indicator.
   * @param array $results
   *   Batch API results array.
   */
  public function finished($success, array $results) {
    if ($success) {
      $message = $this->stringTranslation->formatPlural($results['processed'], 'One user processed.', '@count users processed.');
      $this->messenger->addMessage($message);
    }
    else {
      $message = $this->t('Encountered errors while performing sync.');
      $this->messenger->addMessage($message, 'error');
    }

  }

  /**
   * Get the database connection.
   *
   * This is called directly from the Drupal object to avoid dealing with
   * serialization.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection.
   */
  protected function getDatabase() {
    if (Database::getConnectionInfo('civicrm')) {
      $connection = Database::getConnection('default', 'civicrm');
      return $connection;
    }
    return $this->database;
  }

}
