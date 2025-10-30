<?php

namespace Drupal\redirect_page_by_role;

use Drupal\node\Entity\Node;

/**
 * Provides a common interface for config mapper managers.
 */
interface EnablerRedirectPageByRoleInterface {

  /**
   * Sets a individual node to have redirection rules enabled.
   *
   * @param Drupal\node\Entity\Node $node
   *   Node to set RPBR enabled.
   */
  public function setEnabled(Node $node);

  /**
   * Checks if an individual node has redirection rules enabled.
   *
   * @param Drupal\node\Entity\Node $node
   *   Node to verify if RPBR is enabled.
   *
   * @return bool
   *   Return TRUE if redirection rules is enabled.
   */
  public function isEnabled(Node $node);

  /**
   * Deletes enabled settings for an individual node.
   *
   * @param Drupal\node\Entity\Node $node
   *   Node to set RPBR disabled.
   */
  public function delEnabled(Node $node);

}
