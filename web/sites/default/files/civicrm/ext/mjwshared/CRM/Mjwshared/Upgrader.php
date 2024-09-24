<?php
use CRM_Mjwshared_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Mjwshared_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1000() {
    $this->ctx->log->info('Applying update 1000 - Add civicrm_paymentprocessor_webhook table');
    if (!CRM_Core_DAO::checkTableExists('civicrm_paymentprocessor_webhook')) {
      // Note: this SQL installs an old version of this table which will then
      // be updated by upgrade_1001 It only exists for the sake of people
      // upgrading from old versions.
      $this->executeSqlFile('sql/upgrade_1000.sql');
    }
    return TRUE;
  }

  /**
   * @return TRUE on success
   * @throws Exception
  */
  public function upgrade_1001() {
    $this->ctx->log->info('Applying update 1001 - alter civicrm_paymentprocessor_webhook table');
    $this->executeSqlFile('sql/upgrade_1001.sql');
    return TRUE;
  }

  public function upgrade_1002() {
    $this->ctx->log->info('Add indexes to civicrm_paymentprocessor_webhook table');
    if (!CRM_Core_BAO_SchemaHandler::checkIfIndexExists('civicrm_paymentprocessor_webhook', 'index_processed_date')) {
      CRM_Core_DAO::executeQuery('ALTER TABLE `civicrm_paymentprocessor_webhook` ADD INDEX `index_processed_date` (`processed_date`)');
    }
    if (!CRM_Core_BAO_SchemaHandler::checkIfIndexExists('civicrm_paymentprocessor_webhook', 'index_identifier')) {
      CRM_Core_DAO::executeQuery('ALTER TABLE `civicrm_paymentprocessor_webhook` ADD INDEX `index_identifier` (`identifier`)');
    }
    return TRUE;
  }

  public function upgrade_1003() {
    $this->ctx->log->info('Make "message" field not required');
    CRM_Core_DAO::executeQuery("ALTER TABLE civicrm_paymentprocessor_webhook MODIFY COLUMN `message` varchar(1024) DEFAULT '' COMMENT 'Stores data sent that is needed for processing. JSON suggested.'");
    return TRUE;
  }
}
