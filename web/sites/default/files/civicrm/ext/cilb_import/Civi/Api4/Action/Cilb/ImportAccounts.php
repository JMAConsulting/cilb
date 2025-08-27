<?php

namespace Civi\Api4\Action\Cilb;

/**
 * run with cv api4 on the command line
 *
 * e.g.
 * cv api4 Cilb.import sourceDsn=[] \
 *  cutOffDate=2019-09-01 \
 *  recordLimit=100
 */
class ImportAccounts extends ImportBase {

  protected function import() {
    $this->info('Importing accounts...');

    foreach ($this->getRows("SELECT
         PK_Account_ID,
         Prefix,
         First_Name,
         Middle_Name,
         Last_Name,
         Suffix
      FROM System_Accounts
      WHERE Last_Updated_Timestamp > '{$this->cutOffDate}'
    ") as $account) {

      try {
        \Civi\Api4\Contact::create(FALSE)
          ->addValue('first_name', $account['First_Name'])
          ->addValue('last_name', $account['Last_Name'])
          ->addValue('middle_name', $account['Middle_Name'])
          ->addValue('individual_prefix', $account['Prefix'])
          ->addValue('individual_suffix', $account['Suffix'])
          ->addValue('external_identifier', $account['PK_Account_ID'])
          ->execute();
      }
      catch (\Exception $e) {
        $accountInfo = \json_encode($account, \JSON_PRETTY_PRINT);
        $this->warning(implode("\n", [
          "Error when importing account {$account['PK_Account_ID']}",
          "Error: {$e->getMessage()}",
          "Account: {$accountInfo}",
        ]));
      }
    }
  }

}
