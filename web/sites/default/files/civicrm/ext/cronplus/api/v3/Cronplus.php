<?php

/**
 * Cronplus.execute API
 *
 * Executes the job using cronplus expression
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 */
function civicrm_api3_cronplus_execute($params) {
  $facility = new CRM_Cronplus_JobManager();
  $facility->execute(FALSE);

  // Always creates success - results are handled elsewhere.
  return civicrm_api3_create_success(1, $params, 'Job');
}

/**
 * Cronplus.create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $params description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_cronplus_create_spec(&$params) {
  $params['job_id'] = [
    'name'         => 'job_id',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Job ID',
    'description'  => 'Job ID',
  ];
  $params['cron'] = [
    'name'         => 'cron',
    'api.required' => 1,
    'type'         => CRM_Utils_Type::T_STRING,
    'title'        => 'Cronplus Expression',
    'description'  => 'Expression alla "crontab" to execute the job.',
  ];
}

/**
 * Cronplus.create API
 *
 * Creates/Updates the cronplus entry for a specific job
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_cronplus_create($params) {
  $job_id = $params['job_id'];
  $cron = $params['cron'];
  if (!empty($job_id) && !empty($cron)) {
    $sql = "REPLACE INTO `civicrm_job_scheduled` VALUES (%1, %2);";
    $params  = [
      1 => [$job_id, 'Integer'],
      2 => [strtoupper($cron), 'String'],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $params, FALSE);

    // This is a hack for new Jobs to avoid empty lastRun field
    // It's hard to determine if/when the Job needs to be executed if it has no lastRun
    // So let's imagine it was executed the PreviousRunDate($now)
    $now = CRM_Utils_Date::currentDBDate();
    try {
      $c = Cron\CronExpression::factory($cron);
      $lastCron = $c->getPreviousRunDate($now)->format('YmdHis');
      $sql = "
      UPDATE `civicrm_job`
      SET `last_run` = %1
      WHERE `id` = %2 AND `last_run` IS NULL;";
      $params = [
        1 => [$lastCron, 'String'],
        2 => [$job_id, 'Integer'],
      ];
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
    }
    catch (Exception $e) {
      return civicrm_api3_create_error($e->getMessage());
    }

    return civicrm_api3_create_success(1, $params, 'Create');
  }
  else {
    return civicrm_api3_create_error(E::ts('Mandatory params missing'));
  }
}

/**
 * Cronplus.get API specification
 * This is used for documentation and validation.
 *
 * @param array $params description of fields supported by this API call
 *
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_cronplus_get_spec(&$params) {
  $params['job_id'] = [
    'name' => 'job_id',
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Job ID',
    'description' => 'Job ID',
  ];
}

/**
 * Cronplus.get API.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws API_Exception
 */
function civicrm_api3_cronplus_get($params) {
  try {
    $result = [];
    $job_id = $params['job_id'] ?? NULL;

    if (is_null($job_id)) {
      $sql = "SELECT `job_id`, `cron` FROM `civicrm_job_scheduled`;";
      $dao = CRM_Core_DAO::executeQuery($sql);
    }
    else {
      $sql = "SELECT `job_id`, `cron` FROM `civicrm_job_scheduled` WHERE `job_id` = %1;";
      $dao = CRM_Core_DAO::executeQuery($sql, [
        1 => [$job_id, 'Integer'],
      ]);
    }
    while ($dao->fetch()) {
      $result[] = [
        'id' => $dao->job_id,
        'cron' => $dao->cron,
      ];
    }
  }
  catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
  return civicrm_api3_create_success($result, $params, 'Cronplus', 'get', $dao);
}

/**
 * Cronplus.getsingle API specification
 * This is used for documentation and validation.
 *
 * @param array $params description of fields supported by this API call
 *
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_cronplus_getsingle_spec(&$params) {
  $params['job_id'] = [
    'name' => 'job_id',
    'api.required' => 1,
    'type' => CRM_Utils_Type::T_INT,
    'title' => 'Job ID',
    'description' => 'Job ID',
  ];
}

/**
 * Cronplus.getsingle API.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws API_Exception
 */
function civicrm_api3_cronplus_getsingle($params) {
  try {
    $result = [];
    $job_id = $params['job_id'];

    $sql = "SELECT `job_id`, `cron` FROM `civicrm_job_scheduled` WHERE `job_id` = %1 LIMIT 1;";
    $dao = CRM_Core_DAO::executeQuery($sql, [
      1 => [$job_id, 'Integer'],
    ]);
    if ($dao->fetch()) {
      $result = $dao->cron;
    }
  }
  catch (Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }

  return civicrm_api3_create_success($result, $params, 'Cronplus', 'get', $dao);
}
