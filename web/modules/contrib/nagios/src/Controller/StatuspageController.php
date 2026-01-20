<?php

namespace Drupal\nagios\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\update\UpdateManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

/**
 * Class StatuspageController produces the HTTP output that the bash script in
 * the nagios-plugin directory understands.
 *
 * @package Drupal\nagios\Controller
 */
class StatuspageController {

  use StringTranslationTrait;

  private $config;

  public function __construct() {
    $this->config = \Drupal::config('nagios.settings');
    _nagios_update_os_user();
  }

  /**
   * Main function building the string to show via HTTP.
   *
   * @param string $module_name
   *   Only check the given module with a hook_nagios() implementation.
   * @param string $id_for_hook
   *   An arbitrary value to pass into the hook_nagios() implementation.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function content(string $module_name = '', string $id_for_hook = '') {

    // Disable cache:
    \Drupal::service('page_cache_kill_switch')->trigger();

    // Check the unique ID string and access permissions first.
    $ua = $this->config->get('nagios.ua');
    $request_code = $_SERVER['HTTP_USER_AGENT'];

    // Check if HTTP GET variable "unique_id" is used and the usage is allowed.
    if (isset($_GET['unique_id']) && $this->config->get('nagios.statuspage.getparam') == TRUE) {
      $request_code = $_GET['unique_id'];
    }

    if ($request_code == $ua || \Drupal::currentUser()
      ->hasPermission('administer site configuration')) {
      // Authorized, so go ahead calling all modules:
      $nagios_data = $module_name ?
        [$module_name => call_user_func($module_name . '_nagios', $id_for_hook)] :
        nagios_invoke_all('nagios');
    }
    else {
      // This is not an authorized unique id or user,
      // so just return this default status.
      $nagios_data = [
        'nagios' => [
          'DRUPAL' => [
            'status' => NAGIOS_STATUS_UNKNOWN,
            'type' => 'state',
            'text' => $this->t('Unauthorized'),
          ],
        ],
      ];
    }

    [$output] = $this->getStringFromNagiosData($nagios_data);

    $response = new Response($output, Response::HTTP_OK, ['Content-Type' => 'text/plain']);

    // Disable browser cache:
    $response->setMaxAge(0);
    $response->setExpires(NULL);

    return $response;
  }

  /**
   * Belongs to nagios_check_requirements() function.
   *
   * @param string $tmp_state
   * @param int $max_severity
   *
   * @return string[] Outdated module names
   *
   * @todo compare with functions in nagios.drush.inc; remove repeated code,
   * if possible.
   */
  public function calculateOutdatedModuleAndThemeNames(&$tmp_state, int $max_severity): array {
    static $cache;

    if (!isset($cache[$max_severity])) {
      $cache[$max_severity] = $this->buildModuleList($max_severity);
    }
    [$tmp_modules, $module_list] = $cache[$max_severity];
    if ($tmp_modules) {
      $tmp_modules = trim($tmp_modules);
      $tmp_state .= " ($tmp_modules)";
    }

    return $module_list;
  }

  /**
   * Collect problem categories for module updates.
   */
  private function buildModuleList(int $max_severity): array {
    $tmp_projects = _nagios_get_projects_with_updates($this->config);
    $tmp_modules = '';
    $module_list = [];
    foreach ($tmp_projects as $project_name => $value) {
      if ($value['status'] <= $max_severity && $value['status'] >= UpdateManagerInterface::NOT_SECURE) {
        switch ($value['status']) {
          case UpdateManagerInterface::NOT_SECURE:
            $project_status = $this->t('NOT SECURE');
            break;

          case UpdateManagerInterface::REVOKED:
            $project_status = $this->t('REVOKED');
            break;

          case UpdateManagerInterface::NOT_SUPPORTED:
            $project_status = $this->t('NOT SUPPORTED');
            break;

          case UpdateManagerInterface::NOT_CURRENT:
            $project_status = $this->t('NOT CURRENT');
            break;

          default:
            $project_status = $value['status'];
        }
        $tmp_modules .= ' ' . $project_name . ':' . $project_status;
        $module_list[] = $project_name;
      }
    }

    return [$tmp_modules, $module_list];
  }

  /**
   * Route callback to allow for user-defined URL of status page.
   *
   * @return \Symfony\Component\Routing\Route[]
   */
  public function routes() {
    $config = \Drupal::config('nagios.settings');
    $routes = [];
    // Declares a single route under the name 'example.content'.
    // Returns an array of Route objects.
    $routes['nagios.statuspage'] = new Route(
    // Path to attach this route to:
      $config->get('nagios.statuspage.path') . '/{module_name}/{id_for_hook}',
      // Route defaults:
      [
        '_controller' => $config->get('nagios.statuspage.controller'),
        '_title' => 'Nagios Status',
        'module_name' => '',
        'id_for_hook' => '',
      ],
      // Route requirements:
      [
        '_custom_access' => '\Drupal\nagios\Controller\StatuspageController::access',
      ]
    );

    return $routes;
  }

  /**
   * Checks if the status page should exist.
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function access() {
    $config = \Drupal::config('nagios.settings');

    return AccessResult::allowedIf($config->get('nagios.statuspage.enabled'));
  }

  /**
   * For backwards compatibility, this module uses “define” calls to set levels.
   *
   * This function is called globally in nagios.module.
   *
   * @param \Drupal\Core\Config\ImmutableConfig|null $config
   *   Config to read the values from.
   */
  public static function setNagiosStatusConstants(?ImmutableConfig $config = NULL): void {
    // Defines to be used by this module and others that use its hook_nagios().
    if (!$config) {
      $config = \Drupal::config('nagios.settings');
    }
    if ($config->get('nagios.status.ok') === NULL) {
      // Should only happen in tests, as the config might not be loaded yet.
      return;
    }
    define('NAGIOS_STATUS_OK', $config->get('nagios.status.ok') /* Default: 0 */);
    define('NAGIOS_STATUS_WARNING', $config->get('nagios.status.warning') /* Default: 1 */);
    define('NAGIOS_STATUS_CRITICAL', $config->get('nagios.status.critical') /* Default: 2 */);
    define('NAGIOS_STATUS_UNKNOWN', $config->get('nagios.status.unknown') /* Default: 3 */);
  }

  /**
   * @param array $nagios_data
   *
   * @return array{
   *   0: string,
   *   1: int
   *   }
   */
  public function getStringFromNagiosData(array $nagios_data) {
    // Find the highest level to be the overall status:
    $severity = NAGIOS_STATUS_OK;
    $min_severity = $this->config->get('nagios.min_report_severity');

    $output_state = [];
    $output_perf = [];

    $codes = nagios_status();
    foreach ($nagios_data as $module_name => $module_data) {
      foreach ($module_data as $key => $value) {
        // Check status and set global severity:
        if (is_array($value) && array_key_exists('status', $value) && $value['status'] >= $min_severity) {
          $severity = max($severity, $value['status']);
        }
        switch ($value['type']) {
          case 'state':
            // Complain only if status is larger than minimum severity:
            if ($value['status'] >= $min_severity) {
              $tmp_state = $key . ':' . $codes[$value['status']];
            }
            else {
              $tmp_state = $key . ':' . $codes[NAGIOS_STATUS_OK];
            }

            if (!empty($value['text'])) {
              $tmp_state .= '=' . $value['text'];
            }

            $output_state[] = $tmp_state;
            break;

          case 'perf':
            $output_perf[] = $key . '=' . $value['text'];
            break;
        }
      }
    }

    // Identifier that we check on the bash side:
    $output = "\n" . 'nagios=' . $codes[$severity] . ', ';

    $output .= implode(', ', $output_state) . ' | ' . implode('; ', $output_perf) . "\n";

    return [$output, $severity];
  }

}
