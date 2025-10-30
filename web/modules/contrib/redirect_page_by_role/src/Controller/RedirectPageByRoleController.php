<?php

namespace Drupal\redirect_page_by_role\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\redirect_page_by_role\EnablerRedirectPageByRoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;

/**
 * Controller for Redirect Page By Role.
 */
class RedirectPageByRoleController extends ControllerBase {

  /**
   * Redirect page By Role config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configRedirectPageByRole;

  /**
   * Redirect Page By Role service.
   *
   * @var \Drupal\redirect_page_by_role\EnablerRedirectPageByRoleInterface
   */
  protected $enablerServiceRedirectPageByRole;

  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor for RedirectPageByRoleController.
   *
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param Drupal\redirect_page_by_role\EnablerRedirectPageByRoleInterface $enabler_service_redirect_page_by_role
   *   The Redirect Page By Role service.
   * @param Drupal\Core\Database\Connection $database
   *   Database Service Object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EnablerRedirectPageByRoleInterface $enabler_service_redirect_page_by_role,
    Connection $database
  ) {
    $this->configRedirectPageByRole = $config_factory->get('redirect_page_by_role.settings');
    $this->enablerServiceRedirectPageByRole = $enabler_service_redirect_page_by_role;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('redirect_page_by_role.enabler'),
      $container->get('database')
    );
  }

  /**
   * Check if has any redirection rule for the node content type.
   *
   * @param Drupal\node\Entity\Node $node
   *   Node content type.
   *
   * @return mixed
   *   Returns the type of the rule or FALSE.
   */
  public function contentTypeHasRule(Node $node) {

    $is_enabled = $this->enablerServiceRedirectPageByRole->isEnabled($node);

    if ($is_enabled) {
      return 'node';
    }

    $node_content_type = $node->getType();
    $has_rules = $this->configRedirectPageByRole->get('hasRules');

    if (in_array($node_content_type . '_general', $has_rules)) {
      return 'general';
    }

    return FALSE;
  }

  /**
   * Check and apply redirection rules for the node according to the user role.
   *
   * @param Drupal\node\Entity\Node $node
   *   Node to check redirection rules.
   * @param array $user_roles
   *   Array with user's roles.
   * @param string $rule_type
   *   If node has only general content type rules or specific node rules.
   */
  public function checkRedirect(Node $node, array $user_roles, string $rule_type) {
    // Check if the user has the grant to bypass the redirect rules.
    if ($this->hasBypassRole($user_roles)) {
      return;
    }

    if ($rule_type === 'node') {
      $nid = $node->id();
      $redirect_rules = $this->getNodeRedirectRules($nid);

      // Sort rules by weight.
      usort($redirect_rules, function ($a, $b) {
        return ($a->weight <= $b->weight) ? -1 : 1;
      });

      $redirect_rule = $this->selectRuleByRole($redirect_rules, $user_roles);

      if ($redirect_rule) {
        $this->sendRedirect($redirect_rule);
      }

      return;
    }

    $content_type = $node->getType();
    $redirect_rules = $this->getDefaultRedirectRules($content_type);
    $redirect_rule = $this->selectRuleByRole($redirect_rules, $user_roles);

    if ($redirect_rule) {
      $redirect_rule['status_code'] = $redirect_rules['status_code'];
      $this->sendRedirect($redirect_rule);
    }

  }

  /**
   * Get the content type redirection rules.
   *
   * @param string $content_type
   *   Node content type.
   *
   * @return array
   *   Return array with the rules for the node content type
   *   according to user's roles.
   */
  private function getDefaultRedirectRules(string $content_type) {
    $roles_rules = $this->configRedirectPageByRole->get($content_type);
    $default_redirect_rules = [];

    if (!is_null($roles_rules)) {
      foreach ($roles_rules as $role => $rule) {
        if (empty($rule['redirect_to'])) {
          continue;
        }

        if ($rule["skip_rule"]) {
          return [];
        }

        $default_redirect_rules[$role]['redirect_to'] = $rule['redirect_to'];
        $default_redirect_rules[$role]['weight'] = $rule['weight'];
      }

      if (!empty($default_redirect_rules)) {
        $default_redirect_rules['status_code'] = $this->configRedirectPageByRole->get('default_status_code');
      }
    }

    return $default_redirect_rules;
  }

  /**
   * Get the Node redirection rules.
   *
   * @param string $nid
   *   Node id.
   *
   * @return mixed
   *   Return all specific redirection rules for the node.
   */
  public function getNodeRedirectRules(string $nid) {
    $select = $this->database->select('redirect_page_by_role_node', 'rn')
      ->fields('rn')
      ->condition('nid', $nid);

    return $select->execute()->fetchAll();

  }

  /**
   * Verify if the user has permission to bypass the redirection rules.
   *
   * @param array $user_roles
   *   Array with user's roles.
   *
   * @return bool
   *   True if user has grant to bypass the redirection rules.
   */
  private function hasBypassRole(array $user_roles) {
    $bypass_roles = $this->configRedirectPageByRole->get('bypass_roles');
    foreach ($bypass_roles as $bypass_role) {
      foreach ($user_roles as $user_role) {
        if ($bypass_role == $user_role) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Selects which redirection rule must be sent.
   *
   * @param array $redirection_rules
   *   Array with redirection rules.
   * @param array $user_roles
   *   Array with user's roles.
   *
   * @return mixed
   *   Returns the array with the redirection rule or returns FALSE.
   */
  private function selectRuleByRole(array $redirection_rules, array $user_roles) {
    if (!$redirection_rules) {
      return FALSE;
    }
    foreach ($redirection_rules as $role => $redirection_rule) {
      if (is_object($redirection_rule)) {
        $redirection_rule = json_decode(json_encode($redirection_rule), TRUE);
        $role = $redirection_rule['role'];
      }
      if (in_array($role, $user_roles)) {
        return $redirection_rule;
      }
    }
    return FALSE;
  }

  /**
   * Send redirect.
   *
   * @param array $redirect_rule
   *   Array with redirection info.
   */
  private function sendRedirect(array $redirect_rule) {
    $redirect = new RedirectResponse($redirect_rule['redirect_to'], $redirect_rule['status_code']);
    $redirect->send();
  }

}
