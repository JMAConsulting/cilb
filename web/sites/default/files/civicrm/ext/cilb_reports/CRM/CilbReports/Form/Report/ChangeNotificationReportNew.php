<?php
use CRM_CilbReports_ExtensionUtil as E;
use Civi\Api4\CustomField;

class CRM_CilbReports_Form_Report_ChangeNotificationReportNew extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  private $_temporaryTableName = NULL;

  protected $_customGroupGroupBy = FALSE;

  public function __construct() {
    $this->_columns = [
      'civicrm_contact' => [
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => [
          'id' => [
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'ssn' => [
            'title' => E::ts('SSN'),
            'dbAlias' => 'temp.ssn',
            'required' => TRUE,
          ],
          'entity_id' => [
            'title' => E::ts('Entity ID #'),
            'dbAlias' => 'temp.entity_id',
            'required' => TRUE,
          ],
          'old_last_name' => [
            'title' => E::ts('Candidate Last Name'),
            'dbAlias' => 'temp.old_last_name',
            'required' => TRUE,
          ],
          'old_first_name' => [
            'title' => E::ts('Candidate First Name'),
            'dbAlias' => 'temp.old_first_name',
            'required' => TRUE,
          ],
          'old_middle_name' => [
            'title' => E::ts('Candidate Middle Name'),
            'dbAlias' => 'temp.old_middle_name',
            'required' => TRUE,
          ],
          'old_suffix' => [
            'title' => E::ts('Suffix'),
            'dbAlias' => 'temp.old_suffix',
            'required' => TRUE,
          ],
          'new_last_name' => [
            'title' => E::ts('Correct Candidate Last Name'),
            'dbAlias' => 'temp.new_last_name',
            'required' => TRUE,
          ],
          'new_first_name' => [
            'title' => E::ts('Correct Candidate First Name'),
            'dbAlias' => 'temp.new_first_name',
            'required' => TRUE,
          ],
          'new_middle_name' => [
            'title' => E::ts('Correct Candidate Middle Name'),
            'dbAlias' => 'temp.new_middle_name',
            'required' => TRUE,
          ],
          'new_suffix' => [
            'title' => E::ts('Correct Suffix'),
            'dbAlias' => 'temp.new_suffix',
            'required' => TRUE,
          ],
          'old_email' => [
            'title' => E::ts('Incorrect Email Address'),
            'dbAlias' => 'temp.old_email',
            'required' => TRUE,
          ],
          'new_email' => [
            'title' => E::ts('Correct Email Address'),
            'dbAlias' => 'temp.new_email',
            'required' => TRUE,
          ],
          'old_birth_date' => [
            'title' => E::ts('Incorrect Birthdate'),
            'dbAlias' => 'temp.old_birth_date',
            'required' => TRUE,
          ],
          'new_birth_date' => [
            'title' => E::ts('Correct Birthdate'),
            'dbAlias' => 'temp.new_birth_date',
            'required' => TRUE,
          ],
          'old_address' => [
            'title' => E::ts('Incorrect Address'),
            'dbAlias' => 'temp.old_address',
            'required' => TRUE,
          ],
          'new_address' => [
            'title' => E::ts('Correct Address'),
            'dbAlias' => 'temp.new_address',
            'required' => TRUE,
          ],
          'old_ssn' => [
            'title' => E::ts('Incorrect Social Security'),
            'dbAlias' => 'temp.old_ssn',
            'required' => TRUE,
          ],
          'new_ssn' => [
            'title' => E::ts('Correct Social Security'),
            'dbAlias' => 'temp.new_ssn',
            'required' => TRUE,
          ],
          'old_language' => [
            'title' => E::ts('Incorrect Language for test'),
            'dbAlias' => 'temp.old_language',
            'required' => TRUE,
          ],
          'new_language' => [
            'title' => E::ts('Correct Language for test'),
            'dbAlias' => 'temp.new_language',
            'required' => TRUE,
          ],
          'old_phone' => [
            'title' => E::ts('Incorrect Phone Number'),
            'dbAlias' => 'temp.old_phone',
            'required' => TRUE,
          ],
          'new_phone' => [
            'title' => E::ts('Correct Phone Number'),
            'dbAlias' => 'temp.new_phone',
            'required' => TRUE,
          ],
          'original_registration' => [
            'title' => E::ts('Original Registration'),
            'dbAlias' => 'temp.original_registration',
            'required' => TRUE,
          ],
          'registration_date' => [
            'title' => E::ts('Registration date'),
            'dbAlias' => 'temp.registration_date',
            'required' => TRUE,
          ],
          'change_type' => [
            'title' => E::ts('Change Made'),
            'dbAlias' => 'temp.change_type',
            'required' => TRUE,
          ],
          'changed_date' => [
            'title' => E::ts('Date change was made'),
            'dbAlias' => 'temp.changed_date',
            'required' => TRUE,
          ],
        ],
        'filters' => [
          'sort_name' => [
            'title' => E::ts('Contact Name'),
            'operator' => 'like',
          ],
          'id' => [
            'no_display' => TRUE,
          ],
        ],
        'grouping' => 'contact-fields',
      ],
    ];
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  public function preProcess() {
    $this->assign('reportTitle', E::ts('Change Notification Report'));
    parent::preProcess();
  }

  public function from() {
    $this->_temporaryTableName = $this->createChangeTemporaryTable();
    $this->_from = "
         FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
         INNER JOIN {$this->_temporaryTableName} temp ON temp.contact_id = {$this->_aliases['civicrm_contact']}.id
    ";
  }

  /**
   * Store Where clauses into an array.
   */
  public function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();
    $this->_whereClauses[] = " {$this->_aliases['civicrm_contact']}.is_deleted = 0 ";
  }

  public function whereClause(&$field, $op, $value, $min, $max) {
    return parent::whereClause($field, $op, $value, $min, $max);
  }

  public function alterDisplay(&$rows) {
    $entryFound = FALSE;

    // Re-order the columns to match the requested report layout.
    $fixedHeaders = [];
    $headerOrder = [
      'civicrm_contact_ssn',
      'civicrm_contact_entity_id',
      'civicrm_contact_old_last_name',
      'civicrm_contact_old_first_name',
      'civicrm_contact_old_middle_name',
      'civicrm_contact_old_suffix',
      'civicrm_contact_new_last_name',
      'civicrm_contact_new_first_name',
      'civicrm_contact_new_middle_name',
      'civicrm_contact_new_suffix',
      'civicrm_contact_old_email',
      'civicrm_contact_new_email',
      'civicrm_contact_old_birth_date',
      'civicrm_contact_new_birth_date',
      'civicrm_contact_old_address',
      'civicrm_contact_new_address',
      'civicrm_contact_old_ssn',
      'civicrm_contact_new_ssn',
      'civicrm_contact_old_language',
      'civicrm_contact_new_language',
      'civicrm_contact_old_phone',
      'civicrm_contact_new_phone',
      'civicrm_contact_original_registration',
      'civicrm_contact_registration_date',
      'civicrm_contact_change_type',
      'civicrm_contact_changed_date',
    ];
    $originalColumnHeaders = $this->_columnHeaders;
    foreach ($headerOrder as $header) {
      if (array_key_exists($header, $originalColumnHeaders)) {
        $fixedHeaders[$header] = $originalColumnHeaders[$header];
        unset($originalColumnHeaders[$header]);
      }
    }
    $this->_columnHeaders = $fixedHeaders + $originalColumnHeaders;

    // Look-up maps for option values stored in the temp table.
    $suffixes = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'suffix_id');
    $languages = [];
    $languageOptions = Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('option_group_id.name', '=', 'Registrant_Info_Exam_Language_Preference')
      ->execute();
    foreach ($languageOptions as $languageOption) {
      $languages[$languageOption['value']] = $languageOption['label'];
    }

    foreach ($rows as $rowNum => $row) {
      $entryFound = TRUE;

      // Mask the candidate's SSN, leaving only the last four digits visible.
      if (!empty($row['civicrm_contact_ssn'])) {
        $ssn = $row['civicrm_contact_ssn'];
        $rows[$rowNum]['civicrm_contact_ssn'] = str_replace([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], 'X', substr($ssn, 0, -4)) . substr($ssn, -4);
      }

      // Format the date columns.
      if (!empty($row['civicrm_contact_changed_date'])) {
        $rows[$rowNum]['civicrm_contact_changed_date'] = CRM_Utils_Date::customFormat($row['civicrm_contact_changed_date']);
      }
      if (!empty($row['civicrm_contact_registration_date'])) {
        $rows[$rowNum]['civicrm_contact_registration_date'] = CRM_Utils_Date::customFormat($row['civicrm_contact_registration_date']);
      }
      if (!empty($row['civicrm_contact_old_birth_date'])) {
        $rows[$rowNum]['civicrm_contact_old_birth_date'] = CRM_Utils_Date::customFormat($row['civicrm_contact_old_birth_date']);
      }
      if (!empty($row['civicrm_contact_new_birth_date'])) {
        $rows[$rowNum]['civicrm_contact_new_birth_date'] = CRM_Utils_Date::customFormat($row['civicrm_contact_new_birth_date']);
      }

      // Translate suffix ids to their labels.
      if (!empty($row['civicrm_contact_old_suffix'])) {
        $rows[$rowNum]['civicrm_contact_old_suffix'] = $suffixes[$row['civicrm_contact_old_suffix']] ?? $row['civicrm_contact_old_suffix'];
      }
      if (!empty($row['civicrm_contact_new_suffix'])) {
        $rows[$rowNum]['civicrm_contact_new_suffix'] = $suffixes[$row['civicrm_contact_new_suffix']] ?? $row['civicrm_contact_new_suffix'];
      }

      // Translate exam language preference values to their labels.
      if (!empty($row['civicrm_contact_old_language'])) {
        $rows[$rowNum]['civicrm_contact_old_language'] = $languages[$row['civicrm_contact_old_language']] ?? $row['civicrm_contact_old_language'];
      }
      if (!empty($row['civicrm_contact_new_language'])) {
        $rows[$rowNum]['civicrm_contact_new_language'] = $languages[$row['civicrm_contact_new_language']] ?? $row['civicrm_contact_new_language'];
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  /**
   * Build the temporary table that drives the report.
   *
   * One row is created per candidate per change-type, capturing the most
   * recent change of that type that occurred within the reporting window.
   */
  private function createChangeTemporaryTable(): string {
    $dsn = defined('CIVICRM_LOGGING_DSN') ? CRM_Utils_SQL::autoSwitchDSN(CIVICRM_LOGGING_DSN) : CRM_Utils_SQL::autoSwitchDSN(CIVICRM_DSN);
    $dsn = DB::parseDSN($dsn);
    $loggingDb = $dsn['database'];

    $ssnField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'SSN')
      ->addWhere('custom_group_id.name', '=', 'Registrant_Info')
      ->execute()
      ->first();
    $entityIDField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Entity_ID_imported_')
      ->addWhere('custom_group_id.name', '=', 'cilb_candidate_entity')
      ->execute()
      ->first();
    $languageField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Exam_Language_Preference')
      ->addWhere('custom_group_id.name', '=', 'Candidate_Result')
      ->execute()
      ->first();
    $participantTransactionIDField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Candidate_Payment')
      ->addWhere('custom_group_id.name', '=', 'Participant_Webform')
      ->execute()
      ->first();
    $examPartField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Exam_Part')
      ->addWhere('custom_group_id.name', '=', 'Exam_Details')
      ->execute()
      ->first();
    $adaField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'ADA_Accommodations_Needed')
      ->addWhere('custom_group_id.name', '=', 'Candidate_Result')
      ->execute()
      ->first();

    $homeLocationId = CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_Address', 'location_type_id', 'Home');
    $eventTypeOptionGroupId = CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_OptionValue', 'option_group_id', 'event_type');
    $examPartOptionGroupId = CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_OptionValue', 'option_group_id', 'Exam_Part');

    $lastRunCron = Civi::settings()->get('cilb_reports_changenotification_last_run_date') ?? date('YmdHis', strtotime('-1 week'));

    $temporaryTableName = $this->createTemporaryTable('changed_records_table',
      'id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
       contact_id int unsigned NOT NULL,
       ssn varchar(255) DEFAULT NULL,
       entity_id varchar(255) DEFAULT NULL,
       old_first_name varchar(255) DEFAULT NULL,
       old_middle_name varchar(255) DEFAULT NULL,
       old_last_name varchar(255) DEFAULT NULL,
       old_suffix varchar(255) DEFAULT NULL,
       new_first_name varchar(255) DEFAULT NULL,
       new_middle_name varchar(255) DEFAULT NULL,
       new_last_name varchar(255) DEFAULT NULL,
       new_suffix varchar(255) DEFAULT NULL,
       old_email varchar(255) DEFAULT NULL,
       new_email varchar(255) DEFAULT NULL,
       old_birth_date varchar(255) DEFAULT NULL,
       new_birth_date varchar(255) DEFAULT NULL,
       old_address text DEFAULT NULL,
       new_address text DEFAULT NULL,
       old_ssn varchar(255) DEFAULT NULL,
       new_ssn varchar(255) DEFAULT NULL,
       old_language varchar(255) DEFAULT NULL,
       new_language varchar(255) DEFAULT NULL,
       old_phone varchar(255) DEFAULT NULL,
       new_phone varchar(255) DEFAULT NULL,
       original_registration varchar(255) DEFAULT NULL,
       registration_date datetime DEFAULT NULL,
       change_type varchar(255) NOT NULL DEFAULT \'\',
       changed_date datetime DEFAULT NULL',
      TRUE);
    CRM_Core_DAO::executeQuery("ALTER TABLE {$temporaryTableName} ADD INDEX `index_contact_id`(`contact_id`)");

    // Each actual change found in the logging tables produces its own row. A
    // candidate may therefore appear on several rows (one per change). To avoid
    // duplicate rows for a single change, every field is compared against its
    // value in the immediately preceding log entry (via LAG) rather than against
    // every earlier entry -- so re-saves that don't alter the field (e.g. an
    // address re-saved by geocoding) do not generate phantom rows. Only the
    // column pair relevant to a row's change type is populated; the candidate's
    // name, SSN and Entity ID are filled in for every row further below.

    // Changes to the candidate's name.
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_first_name, old_middle_name, old_last_name, old_suffix, new_first_name, new_middle_name, new_last_name, new_suffix, change_type, changed_date)
      SELECT lg.contact_id, lg.prev_first, lg.prev_middle, lg.prev_last, lg.prev_suffix, lg.first_name, lg.middle_name, lg.last_name, lg.suffix_id, 'Candidate Name Change', lg.log_date
      FROM (
        SELECT lc.id AS contact_id, lc.first_name, lc.middle_name, lc.last_name, lc.suffix_id, lc.log_date,
          LAG(lc.first_name) OVER w AS prev_first,
          LAG(lc.middle_name) OVER w AS prev_middle,
          LAG(lc.last_name) OVER w AS prev_last,
          LAG(lc.suffix_id) OVER w AS prev_suffix,
          LAG(lc.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_civicrm_contact lc
        WINDOW w AS (PARTITION BY lc.id ORDER BY lc.log_date)
      ) lg
      WHERE lg.prev_log_date IS NOT NULL
        AND (lg.first_name != lg.prev_first OR lg.last_name != lg.prev_last OR lg.middle_name != lg.prev_middle)
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Changes to the candidate's birth date.
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_birth_date, new_birth_date, change_type, changed_date)
      SELECT lg.contact_id, lg.prev_birth_date, lg.birth_date, 'Candidate Birthdate Change', lg.log_date
      FROM (
        SELECT lc.id AS contact_id, lc.birth_date, lc.log_date,
          LAG(lc.birth_date) OVER w AS prev_birth_date,
          LAG(lc.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_civicrm_contact lc
        WINDOW w AS (PARTITION BY lc.id ORDER BY lc.log_date)
      ) lg
      WHERE lg.prev_log_date IS NOT NULL
        AND (lg.birth_date != lg.prev_birth_date)
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Changes to the candidate's SSN (ignoring formatting characters).
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_ssn, new_ssn, change_type, changed_date)
      SELECT lg.contact_id, lg.prev_ssn, lg.ssn, 'Candidate SSN Change', lg.log_date
      FROM (
        SELECT lcv.entity_id AS contact_id, lcv.{$ssnField['column_name']} AS ssn, lcv.log_date,
          LAG(lcv.{$ssnField['column_name']}) OVER w AS prev_ssn,
          LAG(lcv.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_{$ssnField['custom_group_id.table_name']} lcv
        WINDOW w AS (PARTITION BY lcv.entity_id ORDER BY lcv.log_date)
      ) lg
      WHERE lg.prev_log_date IS NOT NULL
        AND (REGEXP_REPLACE(lg.ssn, '\\\\D', '') != REGEXP_REPLACE(lg.prev_ssn, '\\\\D', ''))
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Changes to the candidate's home address.
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_address, new_address, change_type, changed_date)
      SELECT lg.contact_id,
        CONCAT(COALESCE(lg.prev_street, ''), '\r\n', COALESCE(lg.prev_city, ''), ' , ', COALESCE(spo.abbreviation, ''), ' ', COALESCE(lg.prev_postal, '')),
        CONCAT(COALESCE(lg.street_address, ''), '\r\n', COALESCE(lg.city, ''), ' , ', COALESCE(spn.abbreviation, ''), ' ', COALESCE(lg.postal_code, '')),
        'Candidate Address Change', lg.log_date
      FROM (
        SELECT lca.contact_id, lca.street_address, lca.supplemental_address_1, lca.city, lca.state_province_id, lca.postal_code, lca.log_date,
          LAG(lca.street_address) OVER w AS prev_street,
          LAG(lca.supplemental_address_1) OVER w AS prev_suppl,
          LAG(lca.city) OVER w AS prev_city,
          LAG(lca.state_province_id) OVER w AS prev_state,
          LAG(lca.postal_code) OVER w AS prev_postal,
          LAG(lca.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_civicrm_address lca
        WHERE lca.location_type_id = {$homeLocationId}
        WINDOW w AS (PARTITION BY lca.contact_id ORDER BY lca.log_date)
      ) lg
      LEFT JOIN civicrm_state_province spo ON spo.id = lg.prev_state
      LEFT JOIN civicrm_state_province spn ON spn.id = lg.state_province_id
      WHERE lg.prev_log_date IS NOT NULL
        AND (lg.street_address != lg.prev_street OR lg.state_province_id != lg.prev_state OR lg.supplemental_address_1 != lg.prev_suppl OR lg.city != lg.prev_city OR lg.postal_code != lg.prev_postal)
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Changes to the candidate's primary email address.
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_email, new_email, change_type, changed_date)
      SELECT lg.contact_id, lg.prev_email, lg.email, 'Candidate Email Change', lg.log_date
      FROM (
        SELECT lce.contact_id, lce.email, lce.log_date,
          LAG(lce.email) OVER w AS prev_email,
          LAG(lce.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_civicrm_email lce
        WHERE lce.is_primary = 1
        WINDOW w AS (PARTITION BY lce.contact_id ORDER BY lce.log_date)
      ) lg
      WHERE lg.prev_log_date IS NOT NULL
        AND (lg.email != lg.prev_email)
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Changes to the candidate's primary phone number.
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_phone, new_phone, change_type, changed_date)
      SELECT lg.contact_id, lg.prev_phone, lg.phone, 'Candidate Phone Change', lg.log_date
      FROM (
        SELECT lcp.contact_id, lcp.phone, lcp.phone_numeric, lcp.log_date,
          LAG(lcp.phone) OVER w AS prev_phone,
          LAG(lcp.phone_numeric) OVER w AS prev_phone_numeric,
          LAG(lcp.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_civicrm_phone lcp
        WHERE lcp.is_primary = 1
        WINDOW w AS (PARTITION BY lcp.contact_id ORDER BY lcp.log_date)
      ) lg
      WHERE lg.prev_log_date IS NOT NULL
        AND (lg.phone_numeric != lg.prev_phone_numeric)
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Changes to the candidate's exam language preference. The custom field
    // lives on the participant, so map it back to the contact.
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_language, new_language, change_type, changed_date)
      SELECT cp.contact_id, lg.prev_language, lg.language, 'Exam Language Change', lg.log_date
      FROM (
        SELECT lcv.entity_id AS participant_id, lcv.{$languageField['column_name']} AS language, lcv.log_date,
          LAG(lcv.{$languageField['column_name']}) OVER w AS prev_language,
          LAG(lcv.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_{$languageField['custom_group_id.table_name']} lcv
        WINDOW w AS (PARTITION BY lcv.entity_id ORDER BY lcv.log_date)
      ) lg
      INNER JOIN civicrm_participant cp ON cp.id = lg.participant_id
      WHERE lg.prev_log_date IS NOT NULL
        AND (lg.language != lg.prev_language)
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Changes to the candidate's ADA accommodation request. The custom field
    // lives on the participant, so map it back to the contact. There is no
    // dedicated column for the ADA value, so this only surfaces that the change
    // occurred (via Change Made).
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, change_type, changed_date)
      SELECT cp.contact_id, 'ADA Accommodation Request', lg.log_date
      FROM (
        SELECT lcv.entity_id AS participant_id, lcv.{$adaField['column_name']} AS ada, lcv.log_date,
          LAG(lcv.{$adaField['column_name']}) OVER w AS prev_ada,
          LAG(lcv.log_date) OVER w AS prev_log_date
        FROM `{$loggingDb}`.log_{$adaField['custom_group_id.table_name']} lcv
        WINDOW w AS (PARTITION BY lcv.entity_id ORDER BY lcv.log_date)
      ) lg
      INNER JOIN civicrm_participant cp ON cp.id = lg.participant_id
      WHERE lg.prev_log_date IS NOT NULL
        AND (lg.ada != lg.prev_ada)
        AND lg.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // For every row that is not itself a name change, show the candidate's
    // current name so the row can be identified. Name-change rows keep the
    // before / after name captured above.
    $sql = "UPDATE {$temporaryTableName} tm
      INNER JOIN civicrm_contact cc ON cc.id = tm.contact_id
      SET tm.old_first_name = cc.first_name,
          tm.old_middle_name = cc.middle_name,
          tm.old_last_name = cc.last_name,
          tm.old_suffix = cc.suffix_id
      WHERE tm.change_type <> 'Candidate Name Change'";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Populate the candidate's current SSN (shown masked).
    $sql = "UPDATE {$temporaryTableName} tm
      INNER JOIN {$ssnField['custom_group_id.table_name']} sv ON sv.entity_id = tm.contact_id
      SET tm.ssn = sv.{$ssnField['column_name']}";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Populate the candidate's imported Entity ID.
    $entityTableName = $this->createTemporaryTable('entity_id', "SELECT entity_id AS contact_id, MAX({$entityIDField['column_name']}) AS entity_id
      FROM {$entityIDField['custom_group_id.table_name']}
      GROUP BY entity_id");
    $sql = "UPDATE {$temporaryTableName} tm INNER JOIN {$entityTableName} e ON e.contact_id = tm.contact_id SET tm.entity_id = e.entity_id";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    // Populate the candidate's most recent registration (event type + exam
    // part) and the date the registration transaction was made.
    $recentRegTableName = $this->createTemporaryTable('recent_registration', "SELECT contact_id, original_registration, registration_date FROM (
        SELECT cp.contact_id,
          CONCAT(COALESCE(etv.label_en_US, ''), ' - ', COALESCE(epv.label_en_US, '')) AS original_registration,
          ct.receive_date AS registration_date,
          ROW_NUMBER() OVER (PARTITION BY cp.contact_id ORDER BY ct.receive_date DESC, cp.id DESC) AS rn
        FROM civicrm_participant cp
        INNER JOIN {$participantTransactionIDField['custom_group_id.table_name']} pv ON pv.entity_id = cp.id
        INNER JOIN civicrm_contribution ct ON ct.id = pv.{$participantTransactionIDField['column_name']}
        INNER JOIN civicrm_event ce ON ce.id = cp.event_id
        LEFT JOIN civicrm_option_value etv ON etv.value = ce.event_type_id AND etv.option_group_id = {$eventTypeOptionGroupId}
        LEFT JOIN {$examPartField['custom_group_id.table_name']} ed ON ed.entity_id = ce.id
        LEFT JOIN civicrm_option_value epv ON epv.value = ed.{$examPartField['column_name']} AND epv.option_group_id = {$examPartOptionGroupId}
        WHERE cp.contact_id IN (SELECT contact_id FROM {$temporaryTableName})
      ) reg WHERE reg.rn = 1");
    $sql = "UPDATE {$temporaryTableName} tm INNER JOIN {$recentRegTableName} rr ON rr.contact_id = tm.contact_id
      SET tm.original_registration = rr.original_registration, tm.registration_date = rr.registration_date";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);

    return $temporaryTableName;
  }

  public function getReportTitle(): string {
    return $this->_title;
  }

}
