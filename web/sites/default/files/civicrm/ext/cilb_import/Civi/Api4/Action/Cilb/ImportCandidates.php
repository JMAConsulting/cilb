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
class ImportCandidates extends ImportBase {

  protected function import() {
    echo 'Importing contacts...';
    $this->importContacts();
    echo 'Importing language preferences...';
    $this->importLanguagePreferences();
    echo 'Importing emails...';
    $this->importEmails();
    echo 'Importing addresses...';
    $this->importAddresses();
    echo 'Importing phones...';
    $this->importPhones();
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

  public function importContacts() {

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

  /**
   * Note: db values are English or Spanish plus one candidate row has NULL
   *
   * Ignore the NULL
   */
  public function importLanguagePreferences() {
    foreach ($this->selectCandidates(['Language_Preference'], ['Language_Preference IS NOT NULL']) as $contact) {
      $langPref = match ($contact['Language_Preference']) {
        // NOTE: Castillian Spanish - do US use MX?
        'Spanish' => 'es_ES',
        'English' => 'en_US',
        default => NULL,
      };

      if (!$langPref) {
        \Civi::log()->warning('Unexpected Language Preference: ' . $contact['Language_Preference']);
      }
      else {
        \Civi\Api4\Contact::update(FALSE)
          ->addWhere('external_identifier', '=', $contact['FK_Account_ID'])
          ->addValue('preferred_language', $langPref)
          ->execute();
      }
    }
  }

  public function importEmails() {
    foreach ($this->selectCandidates(['Email'], ['Email IS NOT NULL']) as $email) {
      if (!$email['Email']) {
        continue;
      }
      \Civi\Api4\Email::create(FALSE)
        ->addValue('email', $email['Email'])
        ->addValue('contact_id.external_identifier', $email['FK_Account_ID'])
        ->execute();
    }
  }

  public function importAddresses() {
    foreach ($this->selectCandidates(['Address1', 'Address2', 'City', 'State', 'Zip']) as $address) {
      if (!array_filter($address)) {
        // if all fields are blank, then skip
        continue;
      }
      \Civi\Api4\Address::create(FALSE)
        ->addValue('contact_id.external_identifier', $address['FK_Account_ID'])
        ->addValue('AddressLine1', $address['Address1'])
        ->addValue('AddressLine2', $address['Address2'])
        ->addValue('City', $address['City'])
        ->addValue('State', $address['State'])
        ->addValue('Zip', $address['Zip'])
        ->execute();
    }
  }

  public function importPhones() {
    foreach ($this->selectCandidates(['Home_Phone', 'Home_Phone_Extension'], ['Home_Phone IS NOT NULL']) as $homePhone) {
      if (!$homePhone['Home_Phone']) {
        // skip if no actual phone number
        continue;
      }
      \Civi\Api4\Phone::create(FALSE)
        ->addValue('location_type_id:name', 'Home')
        ->addValue('phone', $homePhone['Home_Phone'])
        ->addValue('phone_ext', $homePhone['Home_Phone_Extension'])
        ->execute();
    }
    foreach ($this->selectCandidates(['Work_Phone', 'Work_Phone_Extension'], ['Work_Phone IS NOT NULL']) as $workPhone) {
      if (!$workPhone['Work_Phone']) {
        // skip blanks
        continue;
      }
      \Civi\Api4\Phone::create(FALSE)
        ->addValue('location_type_id:name', 'Work')
        ->addValue('phone', $workPhone['Work_Phone'])
        ->addValue('phone_ext', $workPhone['Work_Phone_Extension'])
        ->execute();
    }
  }
}