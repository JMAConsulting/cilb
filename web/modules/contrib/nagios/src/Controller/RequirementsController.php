<?php

namespace Drupal\nagios\Controller;

use Drupal\Core\Config\Config;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\update\UpdateFetcherInterface;
use Drupal\update\UpdateManagerInterface;

/**
 * Get the run-time requirements and status information.
 * module_invoke_all('requirements', 'runtime') returns an array that isn't
 * keyed by the module name, eg we might get a key 'ctools_css_cache'.
 * We have no way of knowing which module set this, and we can't guess based
 * on the name, as removing everything that begins with 'ctools_' might remove
 * data from other ctools sub-modules that we want to still monitor.
 *
 * The only safe way is to use module_invoke, calling each module in turn.
 */
class RequirementsController {

  use StringTranslationTrait;

  private $severity = REQUIREMENT_OK;

  private $config;

  private $reqs = [];

  public function __construct(Config $config) {
    $this->config = $config;
  }

  /**
   * @param string[] $enabled_modules
   * @param array $update_status
   *   An array of installed projects with current update status information.
   * @param $project_data
   */
  public function collectRequirements(array $enabled_modules, array $update_status, $project_data) {
    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler */
    $moduleHandler = \Drupal::service('module_handler');

    foreach ($enabled_modules as $module_name) {
      $requirements_data = $moduleHandler->invoke($module_name, 'requirements', ['runtime']);
      if ($requirements_data && is_array($requirements_data)) {
        // Intercept the Update Status module to respect our Ignore behavior.
        // Note, if $data is empty then there's no available update data and Update Status will handle that for us.
        if ($module_name == 'update' && !empty($update_status)) {
          $this->massageRequirementsFromUpdateModule($requirements_data, $enabled_modules, $project_data['drupal']['includes'], $update_status);
        }
        $this->reqs += $requirements_data;
      }
    }
  }

  /**
   * Sort requirement failures by severity.
   */
  public function findMostSevereProblem(): array {
    $descriptions = [];
    $min_severity = $this->config->get('nagios.min_report_severity');
    $experimental_modules = $this->config->get('nagios.experimental_modules');
    $deprecated_modules = $this->config->get('nagios.deprecated_modules');
    $deprecated_themes = $this->config->get('nagios.deprecated_themes');
    foreach ($this->reqs as $key => $requirement) {
      if (isset($requirement['severity'])) {
        if (($key == 'experimental_modules' && !$experimental_modules)
        || ($key == 'deprecated_modules' && !$deprecated_modules)
        || ($key == 'deprecated_themes' && !$deprecated_themes)) {
          continue;
        }

        // Ignore update_core warning if update check is pending:
        if (($key == 'update_core' || $key == 'update_contrib') && $requirement['severity'] == REQUIREMENT_ERROR && $requirement['reason'] == UpdateFetcherInterface::FETCH_PENDING) {
          continue;
        }
        if ($requirement['severity'] >= $min_severity) {
          if ($requirement['severity'] > $this->severity) {
            $this->severity = $requirement['severity'];
          }
          $descriptions[] = $requirement['title'];
        }
      }
    }
    if (empty($descriptions)) {
      $desc = $this->t('No information.');
    }
    else {
      $desc = implode(', ', $descriptions);
    }
    return [$this->severity, $desc];
  }

  /**
   * Change the array structure.
   */
  private function massageRequirementsFromUpdateModule(&$requirements, array $enabled_modules, $includes, array $update_status): void {
    // Don't want the 'update_contrib' section reported by 'update' module,
    // because that one contains *ALL* modules, even the ones ignored by
    // nagios module.
    unset($requirements['update_contrib']);
    // Now we need to loop through all modules again to reset 'update_contrib'.
    foreach ($enabled_modules as $module_name) {
      // Double check we're not processing a core module.
      if (!array_key_exists($module_name, $includes) && isset($update_status[$module_name]['status']) && is_numeric($update_status[$module_name]['status'])) {
        // Within this clause, only contrib modules are processed. Get
        // update status for the current one, and store data as it would be
        // left by update_requirements() function.
        $contrib_req = _update_requirement_check($update_status[$module_name], 'contrib');
        $contrib_req['name'] = $module_name;
        // If module up to date, set a severity of -1 for sorting purposes.
        if (!isset($contrib_req['severity'])) {
          $contrib_req['severity'] = -1;
        }
        // Build an array of required contrib updates.
        $module_data[] = $contrib_req;
      }
    }

    // Sort our finished array by severity, so we can set Nagios status accordingly.
    if (!empty($module_data)) {
      usort($module_data, '_nagios_updates_sort_by_severity');
      // Add the 'worst case' to requirements.
      $requirements['update_contrib'] = array_pop($module_data);
    }
    if ($requirements && $this->config->get('nagios.show_outdated_names')) {
      end($requirements);
      $controller = new StatuspageController();
      $controller->calculateOutdatedModuleAndThemeNames($requirements[key($requirements)]['title'], UpdateManagerInterface::NOT_CURRENT);
    }
  }

}
