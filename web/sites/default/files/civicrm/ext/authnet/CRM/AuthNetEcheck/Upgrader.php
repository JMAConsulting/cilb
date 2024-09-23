<?php
use CRM_AuthNetEcheck_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_AuthNetEcheck_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed.
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * On postInstall
   */
  public function postInstall() {
    $this->updateLegacyAuthorizeNet();
  }

  /**
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_2100() {
    $this->ctx->log->info('Update legacy Authorize.Net description');
    $this->updateLegacyAuthorizeNet();
    return TRUE;
  }

  private function updateLegacyAuthorizeNet() {
    CRM_Core_DAO::executeQuery('UPDATE `civicrm_payment_processor_type` SET title = "Authorize.Net (legacy)" WHERE title = "Authorize.Net"');
  }

}
