<?php

namespace Civi\Api4\Action\Cilb;

/**
 * The original source data had masked values for SSN
 * and DOB - so this action allows importing these cols
 * again, updating previously imported contacts by
 * matching on FK_Account_Id ~ external_identifier
 */
class UpdateCandidateSsnDob extends ImportBase {

  protected function import() {
    $this->info('Importing contact DOB/SSNs...');
    $this->updateDobSsn();
  }

  protected function selectCandidates(array $fields, array $whereClauses = []) {
    // always want the Account ID (effectively the primary key)
    $fields = array_unique(array_merge($fields, ['FK_Account_ID']));
    $fieldList = implode(', ', $fields);

    // always limit to last 5 years
    $whereClauses[] = "Last_Updated_Timestamp > '{$this->cutOffDate}'";
    $whereList = implode(' AND ', $whereClauses);

    return $this->getRows("SELECT {$fieldList} FROM pti_Candidates WHERE {$whereList}");
  }

  protected function updateDobSsn() {

    foreach ($this->selectCandidates(['DOB', 'SSN']) as $contact) {
      \Civi\Api4\Contact::save(FALSE)
        ->addRecord([
          'external_identifier' => $contact['FK_Account_ID'],
          'birth_date' => $contact['DOB'] ?? NULL,
          'Registrant_Info.SSN' => $contact['SSN'] ?? NULL,
        ])
        ->setMatch(['external_identifier'])
        ->execute();
    }
  }

}
