<?php

namespace Drupal\nagios\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\nagios\Controller\StatuspageController;
use Drupal\update\UpdateManagerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Drush command file for Nagios.
 */
class NagiosCommands extends DrushCommands {

  /**
   * Allows querying Drupal's health. Useful for NRPE.
   *
   * @param string $check
   *   Optionally specify which check to run, e.g. 'cron'.
   *   If missing, all checks are executed.
   *
   * @command nagios
   *
   * @return int
   *   Defaults:
   *   NAGIOS_STATUS_OK: 0
   *   NAGIOS_STATUS_WARNING: 1
   *   NAGIOS_STATUS_CRITICAL: 2
   *   NAGIOS_STATUS_UNKNOWN: 3
   */
  public function nagios($check = '') {
    if ($check) {
      $moduleHandler = \Drupal::moduleHandler();
      if (array_key_exists($check, nagios_functions())) {
        // A specific module has been requested.
        $func = 'nagios_check_' . $check;
        $result = $func();
        $nagios_data['nagios'][$result['key']] = $result['data'];
      }
      elseif ($moduleHandler->moduleExists($check)) {
        $result = $moduleHandler->invoke($check, 'nagios');
        $nagios_data[$check] = $result;
      }
      else {
        $logger = $this->logger();
        $logger->error($check . ' is not a valid nagios check.');
        $logger->error(dt('Run `drush nagios-list` for valid checks.'));

        return 1;
      }
    }
    else {
      $nagios_data = nagios_invoke_all('nagios');
    }

    [$output, $severity] = (new StatuspageController)->getStringFromNagiosData($nagios_data);

    $users = array_unique(_nagios_update_os_user());
    if (count($users) > 1 && $severity != NAGIOS_STATUS_OK) {
      $warning = dt('Warning') . ': ';
      $warning .= dt('All nagios checks should be executed as the same user as the web page.') . "\n";
      $warning .= dt('This is important when modules confirm file system permissions are correct.') . "\n";
      $warning .= dt('You can use `sudo -u` to run drush under a different user.') . "\n";
      $warning .= dt('Use `:command` to delete the state.', [':command' => 'drush state:delete nagios.os_user']) . "\n";
      $warning .= 'User' . print_r($users, TRUE);
      $this->logger()->warning($warning);
    }
    $this->output->writeln($output);
    return $severity;
  }

  /**
   * Prints valid checks for `drush nagios`.
   *
   * @command nagios-list
   * @table-style default
   * @field-labels
   *   check: Check
   *   description: Description
   *   module: Module
   * @default-fields check,description
   * @filter-output
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function nagios_list() {
    $valid_checks = nagios_functions();
    $rows = [];
    foreach ($valid_checks as $check => $description) {
      $rows[$check] = [
        'check' => $check,
        'description' => $description,
        'module' => 'nagios',
      ];
    }

    [$moduleHandler, $module_names] = get_hook_implementations('nagios');
    foreach ($module_names as $name) {
      $info = $moduleHandler->invoke($name, 'nagios_info');
      $description = !empty($info['name']) && is_string($info['name']) ? $info['name'] : '';
      $rows[$name] = [
        'check' => $name,
        'description' => $description,
        'module' => $name,
      ];
    }
    ksort($rows);
    return new RowsOfFields($rows);
  }

  /**
   * Allows querying Drupal's update status. Useful for NRPE.
   *
   * It will respect the nagios.min_report_severity setting.
   *
   * @command nagios-updates
   *
   * @param string $update_type
   *   Optional:
   *   - 'all' to list all updates.
   *   - 'security' to list security updates only.
   *
   * @return int
   *   Defaults:
   *   NAGIOS_STATUS_OK: 0
   *   NAGIOS_STATUS_WARNING: 1
   *   NAGIOS_STATUS_CRITICAL: 2
   *   NAGIOS_STATUS_UNKNOWN: 3
   */
  public function check_updates($update_type = 'all') {
    $logger = $this->logger;
    try {
      \Drupal::service('update.manager');
    }
    catch (ServiceNotFoundException $e) {
      $logger->error(dt('This Drush command is only available if Core’s update module is enabled.'));
      $logger->error(dt('Run `drush en update` to enable it.'));
      return NAGIOS_STATUS_UNKNOWN;
    }

    $status = new StatuspageController();
    $maxSeverity = $update_type == 'all' ? UpdateManagerInterface::NOT_CURRENT : UpdateManagerInterface::NOT_SECURE;
    $module_list = $status->calculateOutdatedModuleAndThemeNames($verbose_output, $maxSeverity);
    $this->writeln($this->output->isVerbose() ? $verbose_output : join("\n", $module_list));
    return count($module_list) ? NAGIOS_STATUS_CRITICAL : NAGIOS_STATUS_OK;
  }

}
