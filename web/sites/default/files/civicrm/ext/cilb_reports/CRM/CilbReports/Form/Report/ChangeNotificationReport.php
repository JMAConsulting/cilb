<?php
use CRM_CilbReports_ExtensionUtil as E;
use Civi\Api4\CustomField;

class CRM_CilbReports_Form_Report_ChangeNotificationReport extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  private $_temporaryTableName = NULL;

  protected $_customGroupExtends = ['Participant', 'Contact', 'Individual', 'Event'];
  protected $_customGroupGroupBy = FALSE;
  public function __construct() {
    $this->_columns = [
      'civicrm_contact' => [
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => [
          'sort_name' => [
            'title' => E::ts('Contact Name'),
            'required' => FALSE,
            'default' => FALSE,
            'no_repeat' => TRUE,
          ],
          'id' => [
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'id' => [
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'display_name' => [
            'title' => E::ts('Candidate Name'),
            'required' => TRUE,
          ],
          'entity_id' => [
            'title' => E::ts('Entity ID'),
            'dbAlias' => 'temp.entity_id',
            'required' => TRUE,
          ],
          'old_value' => [
            'title' => E::ts('Old Value'),
            'dbAlias' => 'temp.old_value',
            'required' => TRUE,
          ],
          'new_value' => [
            'title' => E::ts('New Value'),
            'dbAlias' => 'temp.new_value',
            'required' => TRUE,
          ],
          'changed_by' => [
            'title' => E::ts('Changed By'),
            'dbAlias' => 'temp.changed_by',
            'required' => TRUE,
          ],
          'is_date' => [
            'title' => E::ts('Is Date'),
            'dbAlias' => 'temp.is_date',
            'no_display' => TRUE,
            'required' => TRUE,
          ]
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
    $this->assign('reportTitle', E::ts('Change Notification report'));
    parent::preProcess();
  }

  public function from() {
    $this->_temporaryTableName = $this->createChangeTemporaryTable();
    $this->_from = NULL;
    $participantTransactionIDField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Candidate_Payment')
      ->addWhere('custom_group_id.name', '=', 'Participant_Webform')
      ->execute()
      ->first();

    $this->_from = "
         FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
         INNER JOIN {$this->_temporaryTableName} temp ON temp.contact_id = {$this->_aliases['civicrm_contact']}.id
    ";
    $this->joinAddressFromContact();
    $this->joinEmailFromContact();
  }

  /**
   * Add field specific select alterations.
   *
   * @param string $tableName
   * @param string $tableKey
   * @param string $fieldName
   * @param array $field
   *
   * @return string
   */
  public function selectClause(&$tableName, $tableKey, &$fieldName, &$field) {
    if ($tableName === 'civicrm_value_cilb_candidat_7') {
      if ($fieldName === 'custom_25') {
        $field['dbAlias'] = 'exam_cat.dbpr_code_3';
      }
      else {
        $field['dbAlias'] = '0';
      }
    }
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
  }

  /**
   * Store Where clauses into an array.
   *
   * Breaking out this step makes over-riding more flexible as the clauses can be used in constructing a
   * temp table that may not be part of the final where clause or added
   * in other functions
   */
  public function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();
    $this->_whereClauses[] = " {$this->_aliases['civicrm_contact']}.is_deleted = 0 ";
  }

  /**
   * Add field specific where alterations.
   *
   * This can be overridden in reports for special treatment of a field
   *
   * @param array $field Field specifications
   * @param string $op Query operator (not an exact match to sql)
   * @param mixed $value
   * @param float $min
   * @param float $max
   *
   * @return null|string
   */
  public function whereClause(&$field, $op, $value, $min, $max) {
    return parent::whereClause($field, $op, $value, $min, $max);
  }

  public function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    $checkList = [];
    $eventTypes = [];
    $stateProvinceIDs = [];
    $event_types = Civi::entity('Event')->getOptions('event_type_id', [], TRUE);
    foreach ($event_types as $event_type) {
      $eventTypes[$event_type['id']] = $event_type['label'];
    }
    foreach (array_keys($this->_columnHeaders) as $key) {
    }
    $fixedHeaders = [];
    $headerOrder = [
      'civicrm_contact_display_name',
      'civicrm_contact_entity_id',
      'civicrm_value_registrant_in_1_custom_5',
      'civicrm_contact_old_value',
      'civicrm_contact_new_value',
      'civicrm_contact_changed_by',
    ];
    $originalColumnHeaders = $this->_columnHeaders;
    foreach ($headerOrder as $header) {
      $fixedHeaders[$header] = $originalColumnHeaders[$header];
      unset($originalColumnHeaders[$header]);
    }
    $this->_columnHeaders = $fixedHeaders;
    foreach ($rows as $rowNum => $row) {

      if (!empty($this->_noRepeats) && $this->_outputMode != 'csv') {
        // not repeat contact display names if it matches with the one
        // in previous row
        $repeatFound = FALSE;
        foreach ($row as $colName => $colVal) {
          if (($checkList[$colName] ?? NULL) &&
            is_array($checkList[$colName]) &&
            in_array($colVal, $checkList[$colName])
          ) {
            $rows[$rowNum][$colName] = "";
            $repeatFound = TRUE;
          }
          if (in_array($colName, $this->_noRepeats)) {
            $checkList[$colName][] = $colVal;
          }
        }
      }

      if (array_key_exists('civicrm_contact_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_sort_name'] &&
        array_key_exists('civicrm_contact_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_sort_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (!empty($exam_code)) {
        $rows[$rowNum]['civicrm_value_cilb_candidat_7_custom_31'] = CRM_Core_DAO::singleValueQuery("SELECT entity_id_imported__31 FROM civicrm_value_cilb_candidat_7 WHERE entity_id = %1 AND class_code_18 = %2 LIMIT 1", [
          1 => [$row['civicrm_contact_id'], 'Positive'],
          2 => [$exam_code, 'String'],
        ]);
        $entryFound = TRUE;
      }

      $entryFound = TRUE;
      if (!empty($row['civicrm_contact_changed_by'])) {
        $entryFound = TRUE;
        $rows[$rowNum]['civicrm_contact_changed_by'] = CRM_Core_DAO::singleValueQuery("SELECT display_name FROM civicrm_contact WHERE id = %1", [1 => [$row['civicrm_contact_changed_by'], 'Positive']]);
      }

      if (!empty($row['civicrm_contact_is_date'])) {
        $entryFound = TRUE;
        if (!empty($row['civicrm_contact_old_value'])) {
          $rows[$rowNum]['civicrm_contact_old_value'] = CRM_Utils_Date::customFormat($row['civicrm_contact_old_value']);
        }
        if (!empty($row['civicrm_contact_new_value'])) {
          $rows[$rowNum]['civicrm_contact_new_value'] = CRM_Utils_Date::customFormat($row['civicrm_contact_new_value']);
        }
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  private function createChangeTemporaryTable(): string {
    $dsn = defined('CIVICRM_LOGGING_DSN') ? CRM_Utils_SQL::autoSwitchDSN(CIVICRM_LOGGING_DSN) : CRM_Utils_SQL::autoSwitchDSN(CIVICRM_DSN);
    $dsn = DB::parseDSN($dsn);
    $loggingDb = $dsn['database'];
    $examFormatCustomFieldsDetails = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Exam_Format')
      ->addWhere('custom_group_id.name', '=', 'Exam_Details')
      ->execute()
      ->first();
    $examPartCustomFieldsDetails = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Exam_Part')
      ->addWhere('custom_group_id.name', '=', 'Exam_Details')
      ->execute()
      ->first();
    $entityIDCustomFieldDetails = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Entity_ID_imported_')
      ->addWhere('custom_group_id.name', '=', 'cilb_candidate_entity')
      ->execute()
      ->first();
    $participantTransactionIDField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Candidate_Payment')
      ->addWhere('custom_group_id.name', '=', 'Participant_Webform')
      ->execute()
      ->first();
    $ssnField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'SSN')
      ->addWhere('custom_group_id.name', '=', 'Registrant_Info')
      ->execute()
      ->first();
    $adaField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'ADA_Accommodations_Needed')
      ->addWhere('custom_group_id.name', '=', 'Candidate_Result')
      ->execute()
      ->first();
    $lastRunCron = Civi::settings()->get('cilb_reports_changenotification_last_run_date') ?? date('YmdHis', strtotime('-1 week'));
    $temporaryTableName = $this->createTemporaryTable('changed_records_table','id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, contact_id int unsigned NOT NULL, old_value varchar(255) DEFAULT NULL, new_value varchar(255) DEFAULT NULL, changed_by int NOT NULL default 0, is_date int NOT NULL default 0, entity_id varchar(255) NULL default NULL', TRUE);
    CRM_Core_DAO::executeQuery("ALTER TABLE {$temporaryTableName} ADD INDEX `index_contact_id`(`contact_id`)");
    // Find all changes to names
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_value, new_value, changed_by)
      SELECT DISTINCT lc.id, CONCAT(COALESCE(lc.first_name, ''), ' ', COALESCE(lc.middle_name, ''), ' ', COALESCE(lc.last_name, '')), CONCAT(COALESCE(lc2.first_name, ''), ' ', COALESCE(lc2.middle_name, ''), ' ', COALESCE(lc2.last_name, '')), COALESCE(lc2.log_user_id, 0)
      FROM `{$loggingDb}`.log_civicrm_contact lc
      INNER JOIN `{$loggingDb}`.log_civicrm_contact lc2 ON lc2.id = lc.id AND lc2.log_date > lc.log_date
      WHERE (lc2.first_name != lc.first_name OR lc2.last_name != lc.last_name OR lc2.middle_name != lc.middle_name) AND lc2.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find all changes to birth dates
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_value, new_value, changed_by, is_date)
      SELECT DISTINCT lc.id, COALESCE(lc.birth_date, ''), COALESCE(lc2.birth_date, ''), COALESCE(lc2.log_user_id, 0), 1
      FROM `{$loggingDb}`.log_civicrm_contact lc
      INNER JOIN `{$loggingDb}`.log_civicrm_contact lc2 ON lc2.id = lc.id AND lc2.log_date > lc.log_date
      WHERE (lc2.birth_date != lc.birth_date) AND lc2.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find all changes to SSNs dates
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_value, new_value, changed_by)
      SELECT DISTINCT lc.id, lcv.{$ssnField['column_name']}, lcv2.{$ssnField['column_name']}, COALESCE(lcv2.log_user_id, 0)
      FROM `{$loggingDb}`.log_civicrm_contact lc
      INNER JOIN `{$loggingDb}`.log_{$ssnField['custom_group_id.table_name']} AS lcv ON lcv.entity_id = lc.id
      INNER JOIN `{$loggingDb}`.log_{$ssnField['custom_group_id.table_name']} AS lcv2 ON lcv2.entity_id = lc.id AND lcv2.log_date > lcv.log_date
      WHERE (lcv2.{$ssnField['column_name']} != lcv.{$ssnField['column_name']}) AND lcv2.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    $homeLocationId = CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_Address', 'location_type_id', 'Home');
    // Find all changes to Home Addresses
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_value, new_value, changed_by)
      SELECT DISTINCT lc.id, CONCAT(COALESCE(lca.street_address, ''), '\r\n', COALESCE(lca.city, ''), ' , ', COALESCE(lcas.abbreviation, ''), ' ', COALESCE(lca.postal_code, '')), CONCAT(COALESCE(lca2.street_address, ''), '\r\n', COALESCE(lca2.city, ''), ' , ', COALESCE(lcas2.abbreviation, ''), ' ', COALESCE(lca2.postal_code, '')), COALESCE(lca2.log_user_id, 0)
      FROM `{$loggingDb}`.log_civicrm_contact lc
      LEFT JOIN `{$loggingDb}`.log_civicrm_address lca ON lca.contact_id = lc.id AND lca.location_type_id = {$homeLocationId}
      LEFT JOIN `{$loggingDb}`.log_civicrm_state_province lcas ON lcas.id = lca.state_province_id
      LEFT JOIN `{$loggingDb}`.log_civicrm_address AS lca2 ON lca2.contact_id = lc.id AND lca2.log_date > lca.log_date AND lca.location_type_id = {$homeLocationId}
      LEFT JOIN `{$loggingDb}`.log_civicrm_state_province lcas2 ON lcas2.id = lca2.state_province_id
      WHERE (lca.street_address != lca2.street_address OR lca.state_province_id != lca2.state_province_id OR lca.supplemental_address_1 != lca2.supplemental_address_1 OR lca.city != lca2.city OR lca.postal_code != lca2.postal_code) AND lca2.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find all changes to email addresses
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_value, new_value, changed_by)
      SELECT DISTINCT lc.id, COALESCE(lce.email, ''), COALESCE(lce2.email, ''), COALESCE(lce2.log_user_id, 0)
      FROM `{$loggingDb}`.log_civicrm_contact lc
      LEFT JOIN `{$loggingDb}`.log_civicrm_email lce ON lce.contact_id = lc.id AND lce.is_primary = 1
      LEFT JOIN `{$loggingDb}`.log_civicrm_email AS lce2 ON lce2.contact_id = lc.id AND lce.is_primary = 1 AND lce2.log_date > lce.log_date
      WHERE (lce.email != lce2.email) AND lce2.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find all changes to phones
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_value, new_value, changed_by)
      SELECT DISTINCT lc.id, COALESCE(lcp.phone, ''), COALESCE(lcp2.phone, ''), COALESCE(lcp2.log_user_id, 0)
      FROM `{$loggingDb}`.log_civicrm_contact lc
      LEFT JOIN `{$loggingDb}`.log_civicrm_phone lcp ON lcp.contact_id = lc.id AND lcp.is_primary = 1
      LEFT JOIN `{$loggingDb}`.log_civicrm_phone AS lcp2 ON lcp2.contact_id = lc.id AND lcp.is_primary = 1 AND lcp2.log_date > lcp.log_date
      WHERE (lcp.phone_numeric != lcp2.phone_numeric) AND lcp2.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    $eventTypeOptionGroupId = CRM_Core_PseudoConstant::getKey('CRM_Core_BAO_OptionValue', 'option_group_id', 'event_type');
    // Find all changes to ADA Accomodation
    $sql = "INSERT INTO {$temporaryTableName}(contact_id, old_value, new_value, changed_by)
      SELECT DISTINCT lc.id, IF(lcv.{$adaField['column_name']} = 1, CONCAT('Requested', '\r\n', ov.label_en_US, IF(ov.label_en_US = 'Plumbing' AND ef.{$examFormatCustomFieldsDetails['column_name']} = 'paper', CONCAT('\r\n', COALESCE(ca.city, ''), ',', MONTH(COALESCE(ce.start_date, '')), '/', DAY(COALESCE(ce.start_date, '')), '/', YEAR(COALESCE(ce.start_date, ''))), '')), CONCAT('Not Requested', '\r\n', ov.label_en_US, IF(ov.label_en_US = 'Plumbing' AND ef.{$examFormatCustomFieldsDetails['column_name']} = 'paper', CONCAT('\r\n', COALESCE(ca.city, ''), ',', MONTH(COALESCE(ce.start_date, '')), '/', DAY(COALESCE(ce.start_date, '')), '/', YEAR(COALESCE(ce.start_date, ''))), ''))), IF(lcv2.{$adaField['column_name']} = 1, CONCAT('Requested', '\r\n', ov.label_en_US, IF(ov.label_en_US = 'Plumbing' AND ef.{$examFormatCustomFieldsDetails['column_name']} = 'paper', CONCAT('\r\n', COALESCE(ca.city, ''), ',', MONTH(COALESCE(ce.start_date, '')), '/', DAY(COALESCE(ce.start_date, '')), '/', YEAR(COALESCE(ce.start_date, ''))), '')), CONCAT('Not Requested', '\r\n', ov.label_en_US, IF(ov.label_en_US = 'Plumbing' AND ef.{$examFormatCustomFieldsDetails['column_name']} = 'paper', CONCAT('\r\n', COALESCE(ca.city, ''), ',', MONTH(COALESCE(ce.start_date, '')), '/', DAY(COALESCE(ce.start_date, '')), '/', YEAR(COALESCE(ce.start_date, ''))), ''))), COALESCE(lcv2.log_user_id, 0)
      FROM `{$loggingDb}`.log_civicrm_contact lc
      INNER JOIN `{$loggingDb}`.log_civicrm_participant cp ON cp.contact_id = lc.id
      INNER JOIN `{$loggingDb}`.log_civicrm_event ce ON ce.id = cp.event_id
      INNER JOIN `{$loggingDb}`.log_civicrm_option_value ov ON ov.value = ce.event_type_id AND ov.option_group_id = {$eventTypeOptionGroupId}
      LEFT JOIN `{$loggingDb}`.log_civicrm_loc_block clb ON clb.id = ce.loc_block_id
      LEFT JOIN `{$loggingDb}`.log_civicrm_address ca ON clb.address_id = ca.id
      INNER JOIN `{$loggingDb}`.log_{$examFormatCustomFieldsDetails['custom_group_id.table_name']} AS ef ON ef.entity_id = ce.id
      INNER JOIN `{$loggingDb}`.log_{$adaField['custom_group_id.table_name']} AS lcv ON lcv.entity_id = cp.id
      INNER JOIN `{$loggingDb}`.log_{$adaField['custom_group_id.table_name']} AS lcv2 ON lcv2.entity_id = lcv.entity_id AND lcv2.log_date > lcv.log_date
      WHERE (lcv.{$adaField['column_name']} != lcv2.{$adaField['column_name']}) AND lcv2.log_date >= '{$lastRunCron}'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    $this->createTemporaryTable('entity_id', "SELECT entity_id AS contact_id, LAST_VALUE({$entityIDCustomFieldDetails['column_name']}) AS entity_id
      FROM {$entityIDCustomFieldDetails['custom_group_id.table_name']}
      GROUP BY entity_id");
    $sql = "UPDATE {$temporaryTableName} tm INNER JOIN {$this->temporaryTables['entity_id']['name']} e ON e.contact_id = tm.contact_id SET tm.entity_id = e.entity_id";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    return $temporaryTableName;
  }

  /**
   * Build custom data from clause.
   *
   * @param bool $joinsForFiltersOnly
   *   Only include joins to support filters. This would be used if creating a table of contacts to include first.
   */
  public function customDataFrom($joinsForFiltersOnly = FALSE) {
    if (empty($this->_customGroupExtends)) {
      return;
    }
    $mapper = CRM_Core_BAO_CustomQuery::$extendsMap;
    $customTables = array_column(CRM_Core_BAO_CustomGroup::getAll(), 'table_name');

    foreach ($this->_columns as $table => $prop) {
      if (in_array($table, $customTables)) {
        $extendsTable = $mapper[$prop['extends']];
        // Check field is required for rendering the report.
        if ((!$this->isFieldSelected($prop)) || ($joinsForFiltersOnly && !$this->isFieldFiltered($prop))) {
          continue;
        }
        $baseJoin = $this->_customGroupExtendsJoin[$prop['extends']] ?? "{$this->_aliases[$extendsTable]}.id";

        $customJoin = is_array($this->_customGroupJoin) ? $this->_customGroupJoin[$table] : $this->_customGroupJoin;
        if ($table === 'civicrm_value_cilb_candidat_7') {
          continue;
        }
        $this->_from .= "
{$customJoin} {$table} {$this->_aliases[$table]} ON {$this->_aliases[$table]}.entity_id = {$baseJoin}";
        // handle for ContactReference
        if (array_key_exists('fields', $prop)) {
          foreach ($prop['fields'] as $fieldName => $field) {
            if (($field['dataType'] ?? NULL) === 'ContactReference') {
              $columnName = CRM_Core_BAO_CustomField::getFieldByName($fieldName)['column_name'];
              $this->_from .= "
LEFT JOIN civicrm_contact {$field['alias']} ON {$field['alias']}.id = {$this->_aliases[$table]}.{$columnName} ";
            }
          }
        }
      }
    }
  }

  /**
   * Generate the SELECT clause and set class variable $_select.
   */
  //public function select() {
  //  parent::select();
  //  $this->_select .= ', exam_cat.dbpr_code_3';
  //}

  public function getReportTitle(): string {
    return $this->_title;
  }

}
