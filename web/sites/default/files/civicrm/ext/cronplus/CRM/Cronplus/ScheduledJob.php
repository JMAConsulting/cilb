<?php

class CRM_Cronplus_ScheduledJob {

  /**
   * New implementation of CRM_Core_ScheduleJob.needsRunning()
   *
   * @return bool
   */
  public static function needsRunning($job) {
    // CRM-17686
    // check if the job has a specific scheduled date/time
    if (!empty($job->scheduled_run_date)) {
      if (strtotime($job->scheduled_run_date) <= time()) {
        $job->clearScheduledRunDate();
        return TRUE;
      }
      else {
        return FALSE;
      }
    }

    // run if it's empty - it shouldn't
    if (empty($job->last_run)) {
      return TRUE;
    }

    $now  = CRM_Utils_Date::currentDBDate();
    $sql = "SELECT * FROM `civicrm_job_scheduled` WHERE job_id = %1;";
    $params = [1 => [$job->id, 'Integer']];
    $dao = CRM_Core_DAO::executeQuery($sql, $params);
    if ($dao->fetch()) {
      $cron = $dao->cron;
    }

    // if has no cronplus expression for any reason skip and log
    if (!$cron) {
      Civi::log()->debug("Cronplus expression in Job \"" . $job->name . "\" (" . $job->id . ") is empty. The Job is not processed. Please correct it! ");
      return FALSE;
    }

    $c = Cron\CronExpression::factory($cron);
    $lastCron = $c->getPreviousRunDate($now)->format('YmdHis');
    $lastRun = date('YmdHis', strtotime($job->last_run));

    return ($lastRun < $lastCron);
  }

  /**
   * Returns default cron expression based on core job frequency
   *
   * @return string
   */
  public static function getCronFromFreq($frequency) {
    $mappings = [
      'Always' => '* * * * *',
      'Yearly' => '0 0 1 1 *',
      'Quarter' => '0 0 1 */3 *',
      'Monthly' => '0 0 1 * *',
      'Weekly' => '0 0 * * 0',
      'Daily' => '0 0 * * *',
      'Hourly' => '0 * * * *',
    ];

    if ($frequency) {
      return (isset($mappings[$frequency])) ? $mappings[$frequency] : NULL;
    }

    return NULL;
  }

  /**
   * Returns cron expression from legacy CronPlus format
   * only to be used on 2.0.0 upgrade hook
   *
   * @return string
   */
  public static function getCronFromLegacy($frequency, $params) {
    switch ($frequency) {
      case 'Always':
      case 'Yearly':
      case 'Quarter':
        $cron = CRM_Cronplus_ScheduledJob::getCronFromFreq($frequency);
        break;

      case 'Monthly':
        if ($params['minute'] && $params['hour'] && $params['day']) {
          $cron = $params['minute'] . " " . $params['hour'] . " " . $params['day'] . " * *";
        }
        else {
          $cron = CRM_Cronplus_ScheduledJob::getCronFromFreq($frequency);
        }
        break;

      case 'Weekly':
        if ($params['minute'] && $params['hour'] && $params['weekday']) {
          $cron = $params['minute'] . " " . $params['hour'] . " * * " . $params['weekday'];
        }
        else {
          $cron = CRM_Cronplus_ScheduledJob::getCronFromFreq($frequency);
        }
        break;

      case 'Daily':
        if ($params['minute'] && $params['hour']) {
          $cron = $params['minute'] . " " . $params['hour'] . " * * *";
        }
        else {
          $cron = CRM_Cronplus_ScheduledJob::getCronFromFreq($frequency);
        }
        break;

      case 'Hourly':
        if ($params['minute']) {
          $cron = $params['minute'] . " * * * *";
        }
        else {
          $cron = CRM_Cronplus_ScheduledJob::getCronFromFreq($frequency);
        }
        break;

      default:
        $cron = '* * * * *';
        break;
    }

    return $cron;
  }

}
