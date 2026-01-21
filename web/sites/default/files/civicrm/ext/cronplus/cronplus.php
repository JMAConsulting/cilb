<?php

require_once 'cronplus.civix.php';
// phpcs:disable
use CRM_Cronplus_ExtensionUtil as E;
// phpcs:enable

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
}

/**
 * Implements hook_civicrm_apiWrappers().
 */
function cronplus_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  if ($apiRequest['entity'] == 'Job' && $apiRequest['action'] == 'execute') {
    $wrappers[] = new CRM_Cronplus_APIWrapper();
  }
}

/**
 * Internal function to call Cronplus.execute API
 * MagicFunctionProvider couldn'f find it inside APIWrapper
 */
function _civicrm_api3_cronplus_execute($params) {
  $res = civicrm_api3('Cronplus', 'execute', []);
  return $res;
}

/**
 * Implements hook_civicrm_postSave_civicrm_[table_name]().
 */
function cronplus_civicrm_postSave_civicrm_job($dao) {
  $params = [];
  if ($params['job_id'] = $dao->id) {
    $params['cron'] = strtoupper(CRM_Utils_Array::value('cron', $_POST) ?? "");
    if (empty($params['cron']) && !empty($dao->run_frequency)) {
      $sql = "
        SELECT j.run_frequency, js.job_id, js.cron
        FROM `civicrm_job` j
        LEFT OUTER JOIN `civicrm_job_scheduled` js ON j.id = js.job_id
        WHERE j.id = %1;
      ";
      $sql_params = [1 => [$dao->id, 'Integer']];
      $job_scheduled = CRM_Core_DAO::executeQuery($sql, $sql_params);
      if ($job_scheduled->fetch()) {
        if (empty($job_scheduled->cron)) {
          $params['cron'] = CRM_Cronplus_ScheduledJob::getCronFromFreq($dao->run_frequency);
        }
      }
    }
    if (!empty($params['cron'])) {
      try {
        civicrm_api3('Cronplus', 'create', $params);
      }
      catch (Exception $e) {
        Civi::log()->debug('Cronplus Error: ' . $e->getMessage());
        CRM_Core_Session::setStatus(E::ts('There was an error saving Cronplus configuration. Please contact the SysAdmin'), 'Cronplus Error', 'error');
      }
    }
  }
}

/**
 * Implements hook_civicrm_buildForm().
 *
 */
function cronplus_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Admin_Form_Job') {
    if (in_array($form->getAction(), [CRM_Core_Action::DELETE, CRM_Core_Action::VIEW])) {
      return;
    }

    CRM_Core_Resources::singleton()->addScriptFile('cronplus', 'js/moment.min.js');
    CRM_Core_Resources::singleton()->addScriptFile('cronplus', 'js/later.min.js');
    CRM_Core_Resources::singleton()->addScriptFile('cronplus', 'js/prettycron.js');

    $form->add('text', 'cron', E::ts('CronPlus'), ['size' => CRM_Utils_Type::HUGE], TRUE);

    if ($form->getAction() == CRM_Core_Action::UPDATE) {
      $job_id = $form->getVar('_id');
      $sql = "
    SELECT j.run_frequency, js.job_id, js.cron
    FROM `civicrm_job` j
    LEFT OUTER JOIN `civicrm_job_scheduled` js ON j.id = js.job_id
    WHERE j.id = %1;
    ";
      $params = [1 => [$job_id, 'Integer']];
      $dao = CRM_Core_DAO::executeQuery($sql, $params);
      if ($dao->fetch()) {
        if (!empty($dao->cron)) {
          $defaults['cron'] = $dao->cron;
        }
        else {
          $defaults['cron'] = CRM_Cronplus_ScheduledJob::getCronFromFreq($dao->run_frequency);
        }
      }
      $form->setDefaults($defaults);
    }

    CRM_Core_Region::instance('form-bottom')->add([
      'template' => E::path("templates/CRM/Cronplus/Admin/Form/Cronplus.tpl"),
    ]);

  }
}

/**
 * Implements hook_civicrm_validateForm().
 *
 */
function cronplus_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName == 'CRM_Admin_Form_Job') {
    if (in_array($form->getAction(), [CRM_Core_Action::DELETE, CRM_Core_Action::VIEW])) {
      return;
    }

    $cron = strtoupper(CRM_Utils_Array::value('cron', $fields));
    if (!$cron) {
      $errors['cron'] = E::ts('Cron expression is a required field');
    }
    else {
      try {
        Cron\CronExpression::factory($cron);
      }
      catch (Exception $e) {
        $errors['cron'] = E::ts('Cron expression "' . $cron . '" is invalid');
      }
    }
  }
  return;
}

/**
 * Implements hook_civicrm_pageRun().
 *
 */
function cronplus_civicrm_pageRun(&$page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Admin_Page_Job') {
    CRM_Core_Resources::singleton()->addScriptFile('cronplus', 'js/moment.min.js');
    CRM_Core_Resources::singleton()->addScriptFile('cronplus', 'js/later.min.js');
    CRM_Core_Resources::singleton()->addScriptFile('cronplus', 'js/prettycron.js');

    $cron = [];
    $sql = "SELECT * FROM `civicrm_job_scheduled`";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $cron[$dao->job_id] = $dao->cron;
    }
    $page->assign('cronplus', $cron);
  }
}

/**
 * Implements hook_civicrm_alterTemplateFile().
 *
 */
function cronplus_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  if ($tplName == 'CRM/Admin/Page/Job.tpl') {
    $tplName = 'CRM/Cronplus/Admin/Page/Job.tpl';
  }
}

/**
 * Implementation of hook_civicrm_check
 *
 * Checks for Scheduled jobs without cronplus expression saved
 */
function cronplus_civicrm_check(&$messages) {
  $sql = "
    SELECT id, name
    FROM `civicrm_job` j
    LEFT OUTER JOIN `civicrm_job_scheduled` js
      ON j.id = js.job_id
    WHERE
      j.is_active = 1
      AND cron IS NULL;
  ";
  $dao = CRM_Core_DAO::executeQuery($sql, []);
  $errors = $dao->fetchAll();
  if (!empty($errors)) {
    $output = E::ts("Cronplus expression for these Scheduled Jobs are empty. The Jobs are not being processed. Please correct them! ");
    $output .= "<ul>";
    foreach ($errors as $error) {
      $output .= "<li>" . $error['name'] . " (id: " . $error['id'] . ")";
    }
    $output .= "</ul>";

    $messages[] = new CRM_Utils_Check_Message(
      'cronplus',
      $output,
      E::ts('CronPlus: Active Scheduled Jobs without cron expression'),
      \Psr\Log\LogLevel::WARNING,
      'fa-calendar-times-o'
    );
  }

}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cronplus_civicrm_config(&$config) {
  _cronplus_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cronplus_civicrm_install() {
  _cronplus_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cronplus_civicrm_enable() {
  _cronplus_civix_civicrm_enable();
}
