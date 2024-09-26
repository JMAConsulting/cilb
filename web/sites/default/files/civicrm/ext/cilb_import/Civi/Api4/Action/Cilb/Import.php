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
class Import extends \Civi\Api4\Generic\AbstractAction {

  /**
   * @var string
   *
   * DSN for the source database
   */
  protected string $sourceDsn = '';

  /**
   * @var string
   *
   * cut off date for imports
   * in mysql string format
   */
  protected string $cutOffDate;

  /**
   * @var int
   *
   * max records to select from each table
   * (rough way to do a partial test import)
   */
  protected ?int $recordLimit = NULL;

  /**
   * @var DB
   *
   * DB connection object for the source database
   */
  private $conn;

  public function _run(\Civi\Api4\Generic\Result $result) {

    $this->sourceDsn = $this->sourceDsn ?: getenv('CILB_IMPORT_DSN');

    $this->conn = \DB::connect($this->sourceDsn);

    echo 'Importing candidates...';
    $this->importCandidates();
    echo 'donen';

    echo 'Importing exams...';
    $this->importExams();
    echo 'done\n';

    echo 'Importing registrations...';
    $this->importRegistrations();
    echo 'done\n';

    echo 'Importing activities...';
    $this->importActivities();
    echo 'done\n';

    echo 'Importing blocked users...';
    $this->importBlockedUsers();
    echo 'done\n';

    echo 'Importing candidate entities...';
    $this->importCandidateEntities();
    echo 'done\n';

  }

  protected function getRows(string $query) {
    // add limit clause if set
    $query .= $this->recordLimit ? " LIMIT {$this->recordLimit}" : "";

    $results = $this->conn->query($query);
    while ($row = $results->fetchRow(DB_FETCHMODE_ASSOC)) {
      yield $row;
    }
  }

  public function importCandidates() {
    $this->importContacts();
    $this->importLanguagePreferences();
    $this->importEmails();
    $this->importAddresses();
    $this->importPhones();
  }

  protected function selectCandidates(array $fields, array $whereClauses = []) {
    // always want the Account ID (effectively the primary key)
    $fields = array_unique(array_merge($fields, ['FK_Account_ID']));
    $fieldList = implode(', ', $fields);

    // always limit to last 5 years
    $whereClauses[] = "Last_Updated_Timestamp > '{$this->cutOffDate}'";
    $whereList = implode(' AND ', $whereClauses);

    return $this->getRows("SELECT {$fieldList} FROM pti_candidates WHERE {$whereList}");
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
        Civi::log()->warning('Unexpected Language Preference: ' . $contact['Language_Preference']);
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

  public function importExams() {
    $this->importEventTypes();
    $this->importEvents();
  }

  public function importEventTypes() {

    foreach ($this->getRows("
      SELECT
          `PK_Category_ID`, `Category_Name`, `Specialty_ID`, `Begin_Date`,
          `DBPRCode`, `CategoryID`, `CILB_Class`, `GusCode`, `GusDBPRCode`, `Category_Name_Spanish`
      FROM
          `pti_code_categories`
      ") as $eventCategory) {

      \Civi\Api4\OptionValue::create(FALSE)
        ->addValue('option_group_id.name', 'event_type')
        ->addValue('label', $eventCategory['Category_Name'])
        ->addValue('name', $eventCategory['CategoryID'])
        //->addValue('Exam_Type_Details.begin_date', $eventCategory['Begin_Date'])
        ->addValue('Exam_Type_Details.imported_id', $eventCategory['PK_Category_ID'])
        ->addValue('Exam_Type_Details.Speciality_ID', $eventCategory['Specialty_ID'])
        ->addValue('Exam_Type_Details.DBPR_Code', $eventCategory['DBPRCode'])
        ->addValue('Exam_Type_Details.CILB_Class', $eventCategory['CILB_Class'])
        ->addValue('Exam_Type_Details.Gus_Code', $eventCategory['GusCode'])
        ->addValue('Exam_Type_Details.Gus_DBPR_Code', $eventCategory['GusDBPRCode'])
        ->addValue('Exam_Type_Details.Category_Name_Spanish', $eventCategory['Category_Name_Spanish'])
        ->execute();
    }

  }

  public function importEvents() {

    // TO CHECK: pti_category_exam_parts or pti_code_exam_parts
    // spec says pti_category_exam_parts;
    // but pti_code_exam_parts contains all the data from pti_category_exam_parts
    // as well as the Business and Finance exams, which match the Google Sheet
    // of expected parts for each Exam Category
    foreach ($this->getRows("
    SELECT
        `part`.`PK_Exam_Part_ID`, `part`.`FK_Category_ID`, `part`.`Exam_Part_Name`, `part`.`Exam_Part_Name_Abbr`, `part`.`Exam_Part_Sequence`,

        `category`.`Category_Name`, `category`.`Begin_Date`, `category`.`CategoryID`,

        `dbpr_info`.`Exam_Series_Code`, `dbpr_info`.`Number_Exam_Questions`

    FROM
        `pti_code_exam_parts` as `part`
    JOIN
        `pti_code_categories` as `category`
    ON
        `part`.`FK_Category_ID` = `category`.`PK_Category_ID`
    LEFT JOIN
        `pti_category_exam_parts_dbpr_xref` as `dbpr_info`
    ON
        `part`.`PK_Exam_Part_ID` = `dbpr_info`.`PK_Exam_Part_ID`
      ") as $event) {

      \Civi\Api4\Event::create(FALSE)
        ->addValue('event_type_id:name', $event['CategoryID'])
        ->addValue('start_date', $event['Begin_Date'])
        ->addValue('title', $event['Category_Name'] . ' - ' . $event['Exam_Part_Name'])
        ->addValue('is_online_registration', TRUE)
        ->addValue('Exam_Details.imported_id', $event['PK_Exam_Part_ID'])
        ->addValue('Exam_Details.Exam_Series_Code', $event['Exam_Series_Code'] ?? NULL)
        ->addValue('Exam_Details.Exam_Question_Count', $event['Number_Exam_Questions'] ?? NULL)
          // option values for exam parts created as managed record
          // @see managed/OptionGroup_EventPart.mgd.php
        ->addValue('Exam_Details.Exam_Part', $event['Exam_Part_Name_Abbr'])
        ->addValue('Exam_Details.Exam_Part_Sequence', $event['Exam_Part_Sequence'])
        ->execute();
    }

  }

  public function importRegistrations() {
    foreach ($this->getRows("
        SELECT FK_Account_ID, FK_Category_ID, Confirm_BF_Exam, CategoryID
        FROM pti_exam_registrations
        JOIN pti_code_categories
        ON `FK_Category_ID` = `PK_Category_ID`
        WHERE Transaction_Date > '{$this->cutOffDate}'
    ") as $registration) {

      $contactId = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
        ->execute()->first()['id'] ?? NULL;

      if (!$contactId) {
        \Civi::log()->warning('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
        continue;
      }

      $categoryEvents = \Civi\Api4\Event::get(FALSE)
        ->addWhere('event_type_id:name', '=', $registration['CategoryID']);

      if (!$registration['Confirm_BF_Exam']) {
        $categoryEvents->addWhere('Exam_Details.Exam_Part', '!=', 'BF');
      }

      $categoryEvents = $categoryEvents->execute();

      foreach ($categoryEvents as $event) {
        \Civi\Api4\Participant::create(FALSE)
          ->addValue('event_id', $event['id'])
          ->addValue('contact_id', $contactId)
          ->execute();
      }
    }
  }

  /**
   * import activities from Activity Log table
   *
   * NOTE: we fetch the activity type codes into memory, there's only 5
   */
  public function importActivities() {
    $activityTypes = [];

    foreach ($this->getRows("SELECT PK_Activity_Type, Activity_Type FROM pti_code_activity_type") as $activityType) {
      $activityTypes[$activityType['PK_Activity_Type']] = $activityType['Activity_Type'];
    }

    // ensure the activity types exist in CiviCRM
    foreach ($activityTypes as $name) {
      \Civi\Api4\OptionValue::save(FALSE)
        ->addRecord([
          'name' => $name,
          'label' => $name,
          'option_group_id.name' => 'activity_type',
        ])
        ->setMatch(['name', 'option_group_id'])
        ->execute();
    }

    // now fetch and create the activities themselves
    foreach ($this->getRows("SELECT PK_Activity_Log_ID, FK_Account_ID, Created_Date, Description, FK_Activity_Log_Type_ID, Created_By FROM pti_activity_log WHERE Created_Date > '{$this->cutOffDate}'") as $activity) {

      $targetContact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $activity['FK_Account_ID'])
        ->addSelect('id')
        ->execute()
        ->first();

      if (!$targetContact) {
        // given we have only imported contacts updated in the last 5 years,
        // this query might find some activities created in the last 5 years
        // which link to even older contacts - so this might be fine
        // TODO: check OR limit with a JOIN?
        \Civi::log()->warning("Imported target contact not found for Activity {$activity['PK_Activity_Log_ID']}. Account ID was {$activity['FK_Account_ID']}");
      }

      $sourceContact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $activity['Created_By'])
        ->addSelect('id')
        ->execute()
        ->first();

      if (!$sourceContact) {
        // given we have only imported contacts updated in the last 5 years,
        // this query might find some activities created in the last 5 years
        // which link to even older contacts - so this might be fine
        \Civi::log()->warning("Imported source contact not found for Activity {$activity['PK_Activity_Log_ID']}. Account ID was {$activity['Created_By']}");
        continue;
      }

      $activityTypeName = $activityTypes[$activity['FK_Activity_Log_Type_ID']] ?? NULL;

      if (!$activityTypeName) {
        // activity type not found - something's gone wrong
        throw new \CRM_Core_Exception("Couldn't find imported activity type for code {$activity['FK_Activity_Log_Type_ID']} when importing activity {$activity['PK_Activity_Log_ID']}. Something's wrong :/");
      }

      \Civi\Api4\Activity::create(FALSE)
        ->addValue('target_contact_id', $targetContact['id'])
        ->addValue('source_contact_id', $sourceContact['id'])
        ->addValue('activity_date', $activity['Created_Date'])
        ->addValue('details', $activity['Description'])
        ->addValue('activity_type_id.name', $activityTypeName)
          // put the activity type in the subject as well cause otherwise it's empty
        ->addValue('subject', $activityTypeName)
        ->execute();
    }
  }

  /**
   * Import "restricted candidates" who have been blocked for some reason
   *
   * Note: there is nothing in the source DB that actually links these
   * with other records. They only have SSN and Candidate Name and the SSN
   * don't match any records in pti_candidates
   *
   * We create contacts using these SSNs, then create a linked user and
   * set the status to blocked
   */
  public function importBlockedUsers() {
    foreach ($this->getRows("SELECT SSN, Restriction_Reason, Candidate_Name FROM pti_restricted_candidates") as $blocked) {
      $contact = \Civi\Api4\Contact::create(FALSE)
        ->addValue('display_name', $blocked['Candidate_Name'])
        ->addValue('Registrant_Info.SSN', $blocked['SSN'] ?? NULL)
        ->addValue('Registrant_Info.Restriction_Reason', $blocked['Restriction_Reason'] ?? NULL)
        ->addValue('Registrant_Info.Is_Restricted', TRUE)
        ->execute()->single();

      $pseudoEmail = preg_replace('/[^A-Za-z]/', '', $blocked['Candidate_Name']) . '@blocked.local';

      $params = [
        'cms_name' => $blocked['Candidate_Name'],
        'notify' => FALSE,
        'contact_id' => $contact['id'],
        'email' => $pseudoEmail,
      ];

      $cmsUserId = \CRM_Core_BAO_CMSUser::create($params, 'email');

      $user = \Drupal\user\Entity\User::load($cmsUserId);
      if (!$user) {
        \Civi::log()->warning("No CMS user could be created for contact id {$contact['id']} email {$pseudoEmail}");
        continue;
      }
      $user->block();
      $user->save();
    }
  }

  /**
   * Candidate entity records are imported as a multi-value
   * custom field on Contacts
   *
   * The ClassCode is used to reference an exam category by
   * matching to DBPRCode in `pti_code_categories`
   */
  protected function importCandidateEntities() {
    foreach ($this->getRows("SELECT
        `pti_candidate_entity`.`FK_Account_ID`,
        `pti_candidate_entity`.`Class_Code`,
        `pti_candidate_entity`.`Entity_ID`,
        `pti_code_categories`.`CategoryID`
      FROM `pti_candidate_entity`
      LEFT JOIN `pti_code_categories`
      ON `pti_candidate_entity`.`Class_Code` = `pti_code_categories`.`DBPRCode`
    ") as $candidateEntity) {

      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $candidateEntity['FK_Account_ID'])
        ->addSelect('id')
        ->execute()->first();

      if (!$contact) {
        \Civi::log()->warning("Imported target contact not found for Candidate Entity with Entity ID {$candidateEntity['Entity_ID']}. Account ID was {$candidateEntity['FK_Account_ID']}");
        continue;
      }

      \Civi\Api4\CustomValue::create('cilb_candidate_entity', FALSE)
        ->addValue('entity_id', $contact['id'])
        ->addValue('exam_category:name', $candidateEntity['CategoryID'] ?? NULL)
        ->addValue('class_code', $candidateEntity['Class_Code'] ?? NULL)
        ->execute();
    }
  }
}