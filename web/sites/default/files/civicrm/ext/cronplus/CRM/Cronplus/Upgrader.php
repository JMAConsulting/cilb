<?php
use CRM_Cronplus_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Cronplus_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * Update to 2.0.0
   */
  public function upgrade_2000() {
    $this->executeSqlFile('sql/update_2000/add_column_job_schedule.sql');

    // Adapt old Cronplus format befrore dropping columns
    $sql = "
      SELECT j.id, j.run_frequency, js.*
      FROM `civicrm_job` j
      LEFT OUTER JOIN `civicrm_job_scheduled` js ON j.id = js.job_id
    ";
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    while ($dao->fetch()) {
      $job = [
        'weekday' => $dao->weekday,
        'day' => $dao->day,
        'hour' => $dao->hour,
        'minute' => $dao->minute,
      ];
      $cron = CRM_Cronplus_ScheduledJob::getCronFromLegacy($dao->run_frequency, $job);

      if (!$dao->job_id) {
        $query = "INSERT INTO `civicrm_job_scheduled` (`cron`, `job_id`) VALUES (%1, %2);";
      }
      else {
        $query = "UPDATE `civicrm_job_scheduled` SET `cron` = %1 WHERE `job_id` = %2;";
      }
      $paramsU = [
        1 => [strtoupper($cron), 'String'],
        2 => [$dao->id, 'Integer'],
      ];
      CRM_Core_DAO::executeQuery($query, $paramsU);
    }

    // Drop old columns
    $this->executeSqlFile('sql/update_2000/drop_column_job_schedule.sql');

    return TRUE;
  }

  public function install() {
    $this->executeSqlFile('sql/install.sql');

    // Create Cronplus for existing jobs
    $sql = "SELECT * FROM `civicrm_job`";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $cron = CRM_Cronplus_ScheduledJob::getCronFromFreq($dao->run_frequency);
      $sql = "INSERT INTO `civicrm_job_scheduled` (`job_id`, `cron`) VALUES (%1, %2)";
      $params = [
        1 => [$dao->id, 'Integer'],
        2 => [$cron, 'String'],
      ];
      CRM_Core_DAO::executeQuery($sql, $params);
    }
  }

  public function uninstall() {
    $this->executeSqlFile('sql/uninstall.sql');
  }

}
