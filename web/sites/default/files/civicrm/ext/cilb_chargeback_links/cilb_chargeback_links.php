<?php

require_once 'cilb_chargeback_links.civix.php';

use CRM_CilbChargebackLinks_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function cilb_chargeback_links_civicrm_config(&$config): void {
  _cilb_chargeback_links_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function cilb_chargeback_links_civicrm_install(): void {
  _cilb_chargeback_links_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function cilb_chargeback_links_civicrm_enable(): void {
  _cilb_chargeback_links_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_links().
 * 
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_links
 */
function cilb_chargeback_links_civicrm_links(string $op, string $objectName, $objectID, array &$links, ?int &$mask, array &$values){
  if ($op !== 'participant.selector.row'){
    return;
  }
  $participant = \Civi\Api4\Participant::get(TRUE)
    ->addWhere('id', '=', $objectID)
    ->addSelect('custom.*')
    ->execute()->first();

  $url = $participant['Participant_Webform.Url'];

  if (!empty($url)){
    $links[] = [
      'name' => E::ts('Generate Chargeback'),
      'title' => E::ts('Generate Chargeback'),
      'url' => $url,
      'weight' => 25,
      'class' => 'no-popup'
    ];
  }
}
