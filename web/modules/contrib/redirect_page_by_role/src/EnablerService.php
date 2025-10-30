<?php

namespace Drupal\redirect_page_by_role;

use Drupal\Core\Database\Connection;
use Drupal\node\Entity\Node;

/**
 * Defines a service for managing RSVP list enabled for nodes.
 */
class EnablerService implements EnablerRedirectPageByRoleInterface {

  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   Database Service Object.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnabled(Node $node) {
    if (!$this->isEnabled($node)) {
      $insert = $this->database->insert('redirect_page_by_role_enabled');
      $insert->fields(['nid'], [$node->id()]);
      $insert->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Node $node) {
    if ($node->isNew()) {
      return FALSE;
    }
    $select = $this->database->select('redirect_page_by_role_enabled', 're');
    $select->fields('re', ['nid']);
    $select->condition('nid', $node->id());
    $results = $select->execute();
    return !empty($results->fetchCol());
  }

  /**
   * {@inheritdoc}
   */
  public function delEnabled(Node $node) {
    $delete = $this->database->delete('redirect_page_by_role_enabled');
    $delete->condition('nid', $node->id());
    $delete->execute();
  }

}
