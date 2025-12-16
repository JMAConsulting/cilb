<?php
use CRM_CilbReports_ExtensionUtil as E;
use Civi\Api4\CustomField;

class CRM_CilbReports_Form_Report_MWFReport extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  private $_temporaryTableName = NULL;

  protected $_customGroupExtends = ['Participant', 'Contacts', 'Individual', 'Event'];
  protected $_customGroupGroupBy = FALSE;
  public function __construct() {
    $this->_columns = [
      'civicrm_contact' => [
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => [
          'sort_name' => [
            'title' => E::ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
            'no_repeat' => TRUE,
          ],
          'id' => [
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'first_name' => [
            'title' => E::ts('First Name'),
            'no_repeat' => TRUE,
          ],
          'id' => [
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'last_name' => [
            'title' => E::ts('Last Name'),
            'no_repeat' => TRUE,
          ],
          'id' => [
            'no_display' => TRUE,
            'required' => TRUE,
          ],
          'suffix_id' => [
            'title' => E::ts('Suffix'),
            'required' => TRUE,
          ],
          'middle_name' => [
            'title' => E::ts('Middle Name'),
            'required' => TRUE,
          ],
          'gender' => [
            'title' => E::ts('Gender'),
            'dbAlias' => 'id',
            'required' => TRUE,
          ],
          'race' => [
            'title' => E::ts('Race'),
            'dbAlias' => 'id',
            'required' => TRUE,
          ],
          'birth_date' => [
            'title' => E::ts('Applicatant Birthdate'),
            'required' => TRUE,
            'default' => TRUE,
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
      'civicrm_contribution' => [
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' => [
          'id' => [
            'title' => E::ts('Contribution ID'),
            'required' => TRUE,
          ],
          'receive_date' => [
            'title' => E::ts('Trans Date'),
            'required' => TRUE,
          ],
          'trxn_id' => [
            'title' => E::ts('Trans#'),
            'required' => TRUE,
          ],
        ],
      ],
      'civicrm_participant' => [
        'dao' => 'CRM_Event_BAO_Participant',
        'fields' => [
          'exam_date_change' => [
            'title' => E::ts('Exam Date Chg'),
            'dbAlias' => 'temp.exam_date_change',
            'required' => TRUE,
          ],
          'exam_part_change' => [
            'title' => E::ts('Exam Part Chg'),
            'dbAlias' => 'temp.exam_part_change',
            'required' => TRUE,
          ],
          'exam_event_change' => [
            'title' => E::ts('Exam event Chg'),
            'dbAlias' => 'temp.exam_event_change',
            'required' => TRUE,
          ],
          'candidate_number_change' => [
            'title' => E::ts('Candidate Number Chg'),
            'dbAlias' => 'temp.candidate_number_change',
            'required' => TRUE,
          ],
          'category_change' => [
            'title' => E::ts('category Chg'),
            'dbAlias' => 'temp.category_change',
            'required' => TRUE,
          ],
          'deleted' => [
            'title' => E::ts('Deleted'),
            'dbAlias' => 'temp.deleted',
            'required' => TRUE,
          ],
          'change_type' => [
            'title' => E::ts('Change Type'),
            'dbAlias' => 'temp.change_type',
            'required' => TRUE,
          ],
          'part1' => [
            'title' => E::ts('Part 1'),
            'dbAlias' => 'id',
            'required' => TRUE,
          ],
          'part2' => [
            'title' => E::ts('Part 2'),
            'dbAlias' => 'id',
            'required' => TRUE,
          ],
          'part3' => [
            'title' => E::ts('Part 3'),
            'dbAlias' => 'id',
            'required' => TRUE,
          ],
          'test_site' => [
            'title' => E::ts('Test Site'),
            'required' => TRUE,
            'dbAlias' => 'lba.name',
          ],
        ],
        'grouping' => 'participant-fields',
      ],
      'civicrm_event' => [
        'dao' => 'CRM_Event_DAO_Event',
        'fields' => [
          'start_date' => [
            'title' => E::ts('Exam Date'),
            'required' => TRUE,
          ],
          'event_type_id' => [
            'title' => E::ts('Category'),
            'required' => TRUE,
          ],
        ],
        'grouping' => 'participant-fields',
      ],
      'civicrm_address' => [
        'dao' => 'CRM_Core_DAO_Address',
        'fields' => [
          'street_address' => ['title' => E::ts('Applicant Address'), 'required' => TRUE],
          'supplemental_address_1' => ['title' => E::ts('Applicant Suite'), 'required' => TRUE],
          'city' => ['title' => E::ts('Applicant City'), 'required' => TRUE],
          'postal_code' => ['title' => E::ts('Applicant Zip'), 'required' => TRUE],
          'state_province_id' => ['title' => E::ts('Applicant State'), 'required' => TRUE],
        ],
        'grouping' => 'contact-fields',
      ],
      'civicrm_email' => [
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => ['email' => ['title' => E::ts('Applicant Email'), 'required' => TRUE]],
        'grouping' => 'contact-fields',
      ],
      'civicrm_phone' => [
        'dao' => 'CRM_Core_DAO_Phone',
        'fields' => [
          'phone_home' => ['title' => E::ts('Applicant Home'), 'required' => TRUE, 'dbAlias' => 'home.phone'],
          'phone_work' => ['title' => E::ts('Applicant Work'), 'required' => TRUE, 'dbAlias' => 'work.phone'],
        ],
        'grouping' => 'contact-fields',
      ],
    ];
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  public function preProcess() {
    $this->assign('reportTitle', E::ts('Exam Participant Change report'));
    parent::preProcess();
  }

  public function from() {
    $this->_temporaryTableName = $this->createChangeTemporaryTable();
    $this->_from = NULL;
    $options = \Civi::entity('Phone')->getOptions('location_type_id');
    $workLocationId = $homeLocationId = 0;
    foreach ($options as $option) {
      if ($option['name'] == 'Work') {
        $workLocationId = $option['id'];
      }
      elseif ($option['name'] == 'Home') {
        $homeLocationId = $option['id'];
      }
    }
    $this->_from = "
         FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
               INNER JOIN civicrm_participant {$this->_aliases['civicrm_participant']} ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact']}.id
               INNER JOIN civicrm_line_item cli ON cli.entity_id = {$this->_aliases['civicrm_participant']}.id AND cli.entity_table = 'civicrm_participant'
               INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']}.id = cli.contribution_id
               INNER JOIN civicrm_event {$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id
               INNER JOIN {$this->_temporaryTableName} temp ON temp.transaction_id = {$this->_aliases['civicrm_contribution']}.id
               LEFT JOIN civicrm_loc_block clb ON clb.id = {$this->_aliases['civicrm_event']}.loc_block_id
               LEFT JOIN civicrm_address lba ON lba.id = clb.address_id
               LEFT JOIN civicrm_phone AS home ON home.contact_id = {$this->_aliases['civicrm_contact']}.id AND home.location_type_id = {$homeLocationId}
               LEFT JOIN civicrm_phone AS work ON work.contact_id = {$this->_aliases['civicrm_contact']}.id AND work.location_type_id = {$workLocationId}
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
    return parent::selectClause($tableName, $tableKey, $fieldName, $field);
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
    $event_types = Civi::entity('Event')->getOptions('event_type_id', [], TRUE);
    foreach ($event_types as $event_type) {
      $eventTypes[$event_type['id']] = $event_type['label'];
    }
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

      $examPartCustomFieldsDetails = CustomField::get(FALSE)
        ->addSelect('custom_group_id.table_name', 'column_name')
        ->addWhere('name', '=', 'Exam_Format')
        ->addWhere('custom_group_id.name', '=', 'Exam_Part')
        ->execute()
        ->first();
      $examFormatCustomFieldsDetails = CustomField::get(FALSE)
        ->addSelect('custom_group_id.table_name', 'column_name')
        ->addWhere('name', '=', 'Exam_Format')
        ->addWhere('custom_group_id.name', '=', 'Exam_Details')
        ->execute()
        ->first();
      $participantTransactionIDField = CustomField::get(FALSE)
        ->addSelect('custom_group_id.table_name', 'column_name')
        ->addWhere('name', '=', 'Candidate_Payment')
        ->addWhere('custom_group_id.name', '=', 'Participant_Webform')
        ->execute()
        ->first();
      $partRecords = CRM_Core_DAO::executeQuery("SELECT group_concat(cv.{$examPartCustomFieldsDetails['column_name']}) AS parts
        FROM civicrm_participant cp
        INNER JOIN {$participantTransactionIDField['custom_group_id.name_name']} as ptf ON ptf.entity_id = cp.id
        INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = cp.id
        WHERE ptf.{$participantTransactionIDField['column_name']} = %1
      ", [
        1 => [$row['civicrm_contribution_id'], 'Positive']
      ]);
      $part1 = $part2 = $part3 = '';
      while ($partRecords->fetch()) {
        if (str_contains($partRecords->parts, 'BF')) {
          $part1 = 'BF(CBT)';
        }
        if (str_contains($partRecords->parts, 'TK')) {
          $paperCheck = CRM_Core_DAO::singleValueQuery("SELECT ev.{$examFormatCustomFieldsDetails['column_name']} as exam_format
            FROM civicrm_participant cp
            INNER JOIN {$participantTransactionIDField['custom_group_id.name_name']} as ptf ON ptf.entity_id = cp.id
            INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = cp.id
            INNER JOIN civicrm_event ce ON ce.id = cp.event_id
            INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} AS ev ON ev.entity_id = ce.id
            WHERE ptf.{$participantTransactionIDField['column_name']} = %1
            AND cv.{$examPartCustomFieldsDetails['column_name']} = 'TK'");
          if (empty($part1)) {
            if ($paperCheck == 'paper') {
              $part2 = 'TK';
            }
            else {
              $part2 = 'TK(CBT)';
            }
          }
          else {
            if ($paperCheck == 'paper') {
              $part1 = 'TK';
            }
            else {
              $part1 = 'TK(CBT)';
            }
          }
        }
        if (str_contains($partRecords->parts, 'CA')) {
          if (!empty($part2)) {
            $part3 = 'CA(CBT)';
          }
          elseif (!empty($part1)) {
            $part2 = 'CA(CBT)';
          }
          else {
            $part1 = 'CA(CBT)';
          }
        }
        if (str_contains($partRecords->parts, 'PM')) {
          if (!empty($part3)) {
            continue;
          }
          if (!empty($part2)) {
            $part3 = 'PM(CBT)';
          }
          if (!empty($part1)) {
            $part2 = 'PM(CBT)';
          }
          else {
            $part1 = 'PM(CBT)';
          }
        }
      }

      $rows[$rowNum]['civicrm_participant_part1'] = $part1;
      $rows[$rowNum]['civicrm_participant_part2'] = $part2;
      $rows[$rowNum]['civicrm_participant_part3'] = $part3;
      $rows[$rowNum]['civicrm_contact_gender'] = $rows[$rowNum]['civicrm_contact_race'] = '';
      $entryFound = TRUE;

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

      if (array_key_exists('civicrm_event_event_type_id', $row) && $rows[$rowNum]['civicrm_event_event_type_id']) {
        $rows[$rowNum]['civicrm_event_event_type_id'] = $eventTypes[$row['civicrm_event_event_type_id']];
        $entryFound = TRUE;
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
      ->addWhere('name', '=', 'Exam_Format')
      ->addWhere('custom_group_id.name', '=', 'Exam_Part')
      ->execute()
      ->first();
    $participantCustomFieldDetails = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Candidate_Number')
      ->addWhere('custom_group_id.name', '=', 'Candidate_Result')
      ->execute()
      ->first();
    $participantTransactionIDField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Candidate_Payment')
      ->addWhere('custom_group_id.name', '=', 'Participant_Webform')
      ->execute()
      ->first();
    $lastRunCron = Civi::settings()->get('cilb_reports_mwfreport_last_run_date') ?? date('YmdHis', strtotime('-1 week'));
    $temporaryTableName = $this->createTemporaryTable('changed_records_table','id int unsigned NOT NULL AUTO_INCREMENT, transaction_id int unsigned NOT NULL, exam_date_change int DEFAULT NULL, exam_event_change int DEFAULT NULL, exam_part_change int default NULL, candidate_number_change int DEFAULT NULL, category_change int default NULL, change_type varchar(255) DEFAULT NULL, deleted int NOT NULL default 0, primary_key(id)');
    $sql = "ALTER TABLE {$temporaryTableName} ADD UNIQUE index ui_transaction_id(transaction_id)";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find all New transactions
    $sql = "INSERT INTO {$temporaryTableName},(transaction_id, change_type) SELECT c.id, 'Added'
      FROM civicrm_contribution c
      INNER JOIN `{$loggingDb}`.log_civicrm_contribution lc ON lc.id = c.id AND lc.log_date >= '{$lastRunCron}' AND le.log_action = 'Added'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find all deleted transactions
    $sql = "INSERT INTO {$temporaryTableName},(transaction_id, deleted) SELECT id, 1
      FROM `{$loggingDb}`.log_civicrm_contribution lc
      WHERE lc.log_date >= '{$lastRunCron}' AND le.log_action = 'Deleted'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find where exam date has changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_date_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN {$participantTransactionIDField['custom_group_id.name_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN civicrm_participant p ON p.id = ptf.entity_id
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN `{$loggingDb}`.log_civicrm_event le ON le.id = e.id WHERE le.log_date >= '{$lastRunCron}' AND le.log_action = 'Update' AND (le.start_date != e.start_date OR le.end_date != e.end_date) AND le.event_id = e.id
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find where the exam has changed but the exam part is still the same
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_event_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN {$participantTransactionIDField['custom_group_id.name_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN civicrm_participant p ON p.id = ptf.entity_id
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id != e.id AND cv.{$examPartCustomFieldsDetails['column_name']} = lcv.{$examPartCustomFieldsDetails['column_name']}
      ON DUPLICATE KEY UPDATE exam_event_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find where the exam has changed and the exam part has changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_part_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN {$participantTransactionIDField['custom_group_id.name_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN civicrm_participant p ON p.id = ptf.entity_id
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id != e.id AND cv.{$examPartCustomFieldsDetails['column_name']} != lcv.{$examPartCustomFieldsDetails['column_name']}
      ON DUPLICATE KEY UPDATE exam_part_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find cases where an examp part has been removed and flag as exam part changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_part_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN `{$loggingDb}`.{$participantTransactionIDField['custom_group_id.name_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant p ON p.id = ptf.entity_id
      WHERE ptf.log_date >= '{$lastRunCron}' AND ptf.log_action = 'Delete'
      ON DUPLICATE KEY UPDATE exam_part_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find where the exam has changed and the Category has changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, category_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN `{$loggingDb}`.log_civicrm_event lce ON lce.id = lcp.event_id
      INNER JOIN `{$loggingDb}`.{$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id != e.id AND lce.event_type_id != e.event_type_id
      ON DUPLICATE KEY UPDATE category_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find where the candidate number has changed and we have the same exam.
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, candidate_number_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN {$participantCustomFieldDetails['custom_group_id.table_name']} as pcv On pcv.entity_id = p.id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN `{$loggingDb}`.log_civicrm_event lce On lce.id = lcp.event_id
      INNER JOIN `{$loggingDb}`.{$participantCustomFieldDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id = e.id AND lce.event_type_id = e.event_type_id AND pcv.{$participantCustomFieldDetails['column_name']} != lcv.{$participantCustomFieldDetails['column_name']}
      ON DUPLICATE KEY UPDATE candidate_number_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    return $temporaryTableName;
  }

}
