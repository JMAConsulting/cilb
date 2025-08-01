<?php

require_once 'cilb_reports.civix.php';

use CRM_CilbReports_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cilb_reports_civicrm_config(&$config): void {
  _cilb_reports_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cilb_reports_civicrm_install(): void {
  _cilb_reports_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cilb_reports_civicrm_enable(): void {
  _cilb_reports_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Add our extra afform dependencies
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildForm
 */
function cilb_reports_civicrm_buildForm(string $formName): void {
  $modules = match ($formName) {
    'CRM_Contribute_Form_ContributionView' => 'afsearchCandidatesForPayment',
    'CRM_Event_Form_ParticipantView' => 'afsearchCandidatesForPayment',
    default => NULL,
  };
  if ($modules) {
    \Civi::service('angularjs.loader')->addModules($modules);
  }
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Include link to Candidate Payment
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterCustomFieldDisplayValue
 */
function cilb_reports_civicrm_alterCustomFieldDisplayValue(&$displayValue, $value, $entityID, $fieldInfo) {
  switch ($fieldInfo['name']) {
    case 'Candidate_Payment':

      $contribution = \Civi\Api4\Contribution::get(FALSE)
        ->addSelect('contact_id.display_name', 'total_amount', 'receive_date')
        ->addWhere('id', '=', $value)
        ->execute()
        ->first();

      if (!$contribution) {
        return;
      }

      $name = $contribution['contact_id.display_name'];
      $amount = \CRM_Utils_Money::format($contribution['total_amount']);
      $date = $contribution['receive_date'] ?: E::ts('date missing');

      $recordText = "{$name} - {$amount} - {$date}";
      $buttonUrl = "/civicrm/contact/view/contribution?reset=1&id={$value}&action=view&context=participant&selectedChild=contribute";
      $buttonText = E::ts('View payment');
      $displayValue = "
        <span>{$recordText}</span>
        <a
          class='btn btn-primary'
          target='crm-popup'
          href='{$buttonUrl}'
          >
          <i class='crm-i fa-credit-card'></i>
          {$buttonText}
        </a>
      ";
      break;

  }
}
