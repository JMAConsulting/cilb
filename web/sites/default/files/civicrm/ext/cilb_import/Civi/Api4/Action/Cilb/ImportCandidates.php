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
    $this->info('Importing contacts...');
    $this->importContacts();
    $this->info('Importing language preferences...');
    $this->importLanguagePreferences();
    $this->info('Importing emails...');
    $this->importEmails();
    $this->info('Importing addresses...');
    $this->importAddresses();
    $this->info('Importing phones...');
    $this->importPhones();
  }

  protected function selectCandidates(array $fields, array $whereClauses = []) {
    // always want the Account ID (effectively the primary key)
    $fields = array_unique(array_merge($fields, ['FK_Account_ID']));
    $fieldList = implode(', ', $fields);

    // always limit to last 5 years
    $whereClauses[] = "c.Last_Updated_Timestamp > '{$this->cutOffDate}'";
    $whereList = implode(' AND ', $whereClauses);

    return $this->getRows("SELECT {$fieldList} FROM pti_Candidates c INNER JOIN System_Accounts a ON a.PK_Account_ID = c.FK_Account_ID WHERE {$whereList}");
  }

  public function importContacts() {

    foreach ($this->selectCandidates(['DOB', 'SSN', 'Prefix', 'Suffix', 'First_Name', 'Middle_Name', 'Last_Name']) as $contact) {
      if (!empty($contact['Suffix'])) {
      $suffix = \Civi\Api4\OptionValue::get(FALSE)
        ->addWhere('option_group_id:name', '=', 'individual_suffix')
        ->addWhere('label', '=', $contact['Suffix'])
        ->execute();

      if (!$suffix->count()) {
        \Civi\Api4\OptionValue::create(FALSE)
          ->addValue('option_group_id:name', 'individual_suffix')
          ->addValue('label', $contact['Suffix'])
          ->execute();
      }
      }
      else {
        $contact['Suffix'] = NULL;
      }

      if (!empty($contact['Prefix'])) {
      $prefix = \Civi\Api4\OptionValue::get(FALSE)
        ->addWhere('option_group_id:name', '=', 'individual_prefix')
        ->addWhere('label', '=', $contact['Prefix'])
        ->execute();

      if (!$prefix->count()) {
        \Civi\Api4\OptionValue::create(FALSE)
          ->addValue('option_group_id:name', 'individual_prefix')
          ->addValue('label', $contact['Prefix'])
          ->execute();
      }
      }
      else {
        $contact['Prefix'] = NULL;
      }
      
      \Civi\Api4\Contact::save(FALSE)
        ->addRecord([
          'external_identifier' => $contact['FK_Account_ID'],
          'birth_date' => $contact['DOB'] ?? NULL,
          'Registrant_Info.SSN' => $contact['SSN'] ?? NULL,
          'first_name' => $contact['First_Name'],
          'middle_name' => $contact['Middle_Name'],
          'last_name' => $contact['Last_Name'],
          'contact_type' => 'Individual',
          'contact_sub_type' => [
            'Candidate',
          ],
          'suffix_id:label' => $contact['Suffix'],
          'prefix_id:label' => $contact['Prefix'],
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
        $this->warning('Unexpected Language Preference: ' . $contact['Language_Preference']);
      }
      else {
        \Civi\Api4\Contact::update(FALSE)
          ->addWhere('external_identifier', '=', $contact['FK_Account_ID'])
          ->addValue('preferred_language', $langPref)
          ->execute();
      }
    }
  }

  protected function getContactId($accountId): ?int {
    $id = \Civi\Api4\Contact::get(FALSE)
      ->addSelect('id')
      ->addWhere('external_identifier', '=', $accountId)
      ->execute()
      ->first()['id'] ?? NULL;

    if (!$id) {
      $this->warning("Contact ID not found when importing linked data for Account ID {$accountId}");
    }
    return $id;
  }

  public function importEmails() {
    foreach ($this->selectCandidates(['Email'], ['Email IS NOT NULL']) as $email) {
      if (!$email['Email']) {
        continue;
      }
      $contactId = $this->getContactId($email['FK_Account_ID']);
      \Civi\Api4\Email::create(FALSE)
        ->addValue('email', $email['Email'])
        ->addValue('contact_id', $contactId)
        ->execute();
    }
  }

  public function importAddresses() {
    $states = \Civi\Api4\StateProvince::get(FALSE)
      ->addWhere('country_id', '=', 1228)
      ->execute()
      ->indexBy('abbreviation')
      ->column('id');

    foreach ($this->selectCandidates(['Address1', 'Address2', 'City', 'State', 'Zip']) as $address) {
      if (!array_filter($address)) {
        // if all fields are blank, then skip
        continue;
      }
      $contactId = $this->getContactId($address['FK_Account_ID']);
      $state = $states[$address['State'] ?? 'NONE'] ?? NULL;

      \Civi\Api4\Address::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('street_address', $address['Address1'])
        ->addValue('supplemental_address_1', $address['Address2'])
        ->addValue('city', $address['City'])
        ->addValue('state_province_id', $state)
        ->addValue('postal_code', $address['Zip'])
        ->execute();
    }
  }

  public function importPhones() {
    foreach ($this->selectCandidates(['Home_Phone', 'Home_Phone_Extension'], ['Home_Phone IS NOT NULL']) as $homePhone) {
      if (!$homePhone['Home_Phone']) {
        // skip if no actual phone number
        continue;
      }
      $contactId = $this->getContactId($homePhone['FK_Account_ID']);
      \Civi\Api4\Phone::create(FALSE)
        ->addValue('contact_id', $contactId)
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
      $contactId = $this->getContactId($workPhone['FK_Account_ID']);
      \Civi\Api4\Phone::create(FALSE)
        ->addValue('contact_id', $contactId)
        ->addValue('location_type_id:name', 'Work')
        ->addValue('phone', $workPhone['Work_Phone'])
        ->addValue('phone_ext', $workPhone['Work_Phone_Extension'])
        ->execute();
    }
  }
}
