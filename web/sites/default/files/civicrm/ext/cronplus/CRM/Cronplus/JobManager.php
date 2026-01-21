<?php

use Civi\Api4\Job;

class CRM_Cronplus_JobManager extends CRM_Core_JobManager {

  /**
   * @param bool $auth
   */
  public function execute($auth = TRUE) {

    $this->logEntry('Starting scheduled jobs execution');

    if ($auth && !CRM_Utils_System::authenticateKey(TRUE)) {
      $this->logEntry('Could not authenticate the site key.');
    }
    require_once 'api/api.php';

    // it's not asynchronous at this stage
    CRM_Utils_Hook::cron($this);

    // Get a list of the jobs that have completed previously
    $successfulJobs = Job::get(FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->addClause('OR', ['last_run', 'IS NULL'], ['last_run', '<=', 'last_run_end', TRUE])
      ->addOrderBy('name', 'ASC')
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    // Get a list of jobs that have not completed previously.
    // This could be because they are a new job that has not yet run or a job that is fatally crashing (eg. OOM).
    // If last_run is NULL the job has never run and will be selected above so exclude it here
    // If last_run_end is NULL the job has never completed successfully.
    // If last_run_end is < last_run job has completed successfully in the past but is now failing to complete.
    $maybeUnsuccessfulJobs = Job::get(FALSE)
      ->addWhere('is_active', '=', TRUE)
      ->addWhere('last_run', 'IS NOT NULL')
      ->addClause('OR', ['last_run_end', 'IS NULL'], ['last_run', '>', 'last_run_end', TRUE])
      ->addOrderBy('name', 'ASC')
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    $jobs = array_merge($successfulJobs, $maybeUnsuccessfulJobs);
    foreach ($jobs as $job) {
      $temp = ['class' => NULL, 'parameters' => NULL, 'last_run' => NULL];
      $scheduledJobParams = array_merge($temp, $job);
      $jobDAO = new CRM_Core_ScheduledJob($scheduledJobParams);

      if (CRM_Cronplus_ScheduledJob::needsRunning($jobDAO)) {
        $this->executeJob($jobDAO);
      }
    }

    $this->logEntry('Finishing scheduled jobs execution.');

    // Set last cron date for the status check
    $statusPref = [
      'name' => 'checkLastCron',
      'check_info' => gmdate('U'),
      'prefs' => '',
    ];
    CRM_Core_BAO_StatusPreference::create($statusPref);
  }

}
