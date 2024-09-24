<?php
/**
 * https://civicrm.org/licensing
 */

require_once 'authnetecheck.civix.php';
require_once __DIR__.'/vendor/autoload.php';

use CRM_AuthNetEcheck_ExtensionUtil as E;

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function authnetecheck_civicrm_config(&$config) {
  _authnetecheck_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function authnetecheck_civicrm_install() {
  _authnetecheck_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function authnetecheck_civicrm_postInstall() {
  // Create an Direct Debit Payment Instrument
  CRM_Core_Payment_MJWTrait::createPaymentInstrument(['name' => 'EFT']);
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function authnetecheck_civicrm_enable() {
  _authnetecheck_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_check().
 */
function authnetecheck_civicrm_check(&$messages) {
  $checks = new CRM_AuthorizeNet_Check($messages);
  $messages = $checks->checkRequirements();
  // If we didn't install mjwshared yet check requirements but don't crash when checking webhooks
  if (trait_exists('CRM_Mjwshared_WebhookTrait')) {
    CRM_AuthorizeNet_Webhook::check($messages);
  }
}

/**
 * Implements hook_civicrm_validateForm().
 */
function authnetecheck_civicrm_validateForm($formName, $fields, $files, $form, &$errors) {
  if ($formName == 'CRM_Admin_Form_PaymentProcessor') {
    $paymentProcessor = $form->getVar('_paymentProcessorDAO');
    if ($paymentProcessor && $paymentProcessor->class_name == 'Payment_AuthNetCreditcard') {
      $sig = $fields['signature'];
      if ($sig !== NULL && $sig !== '' && (strlen($sig) < 100 || strpos($sig, ' ') !== FALSE)) {
        $errors['signature'] = E::ts('Please enter a valid signature key. You can generate one in your portal at authorize.net under Account >> API Credentials.');
      }
    }
  }
}
