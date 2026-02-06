<?php
use CRM_CilbReports_ExtensionUtil as E;
use Civi\Api4\CustomField;

class CRM_CilbReports_Form_Report_MWFReport extends CRM_Report_Form {

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
            'dbAlias' => 'suffix_ov.label',
            'required' => TRUE,
          ],
          'middle_name' => [
            'title' => E::ts('Middle Name'),
            'required' => TRUE,
          ],
          'gender_id' => [
            'title' => E::ts('Gender'),
            'dbAlias' => 'gender_ov.label',
            'required' => TRUE,
          ],
          'birth_date' => [
            'title' => E::ts('Applicant BirthDate'),
            'required' => TRUE,
            'default' => TRUE,
          ],
          'external_identifier' => [
            'title' => E::ts('PTI Acct ID'),
            'required' => TRUE,
            'default' => TRUE,
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
      'civicrm_contribution' => [
        'dao' => 'CRM_Contribute_DAO_Contribution',
        'fields' => [
          'id' => [
            'title' => E::ts('Contribution ID'),
            'required' => TRUE,
            'no_display' => TRUE,
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
        'group_bys' => [
          'id' => [
            'title' => E::ts('Contribution ID'),
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
            'title' => E::ts('Exam Event Chg'),
            'dbAlias' => 'temp.exam_event_change',
            'required' => TRUE,
          ],
          'candidate_number_change' => [
            'title' => E::ts('Cand Num Chg'),
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
            'dbAlias' => 'temp.id',
            'required' => TRUE,
          ],
          'part2' => [
            'title' => E::ts('Part 2'),
            'dbAlias' => 'temp.id',
            'required' => TRUE,
          ],
          'part3' => [
            'title' => E::ts('Part 3'),
            'dbAlias' => 'temp.id',
            'required' => TRUE,
          ],
          'test_site' => [
            'title' => E::ts('Test Site'),
            'required' => TRUE,
            'dbAlias' => 'lba.city',
          ],
        ],
        'grouping' => 'participant-fields',
      ],
      'civicrm_event' => [
        'dao' => 'CRM_Event_DAO_Event',
        'fields' => [
          'event_type_id' => [
            'title' => E::ts('Category'),
            'required' => TRUE,
          ],
          'start_date' => [
            'title' => E::ts('Exam Date'),
            'required' => TRUE,
          ],
        ],
        'group_bys' => [
          'event_type_id' => [
            'title' => E::ts('Category'),
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
    $workLocationId = $homeLocationId = $mainLocationId = 0;
    foreach ($options as $option) {
      if ($option['name'] == 'Work') {
        $workLocationId = $option['id'];
      }
      elseif ($option['name'] == 'Home') {
        $homeLocationId = $option['id'];
      }
      elseif ($option['name'] == 'Main') {
        $mainLocationId = $option['id'];
      }
    }
    $participantTransactionIDField = CustomField::get(FALSE)
      ->addSelect('custom_group_id.table_name', 'column_name')
      ->addWhere('name', '=', 'Candidate_Payment')
      ->addWhere('custom_group_id.name', '=', 'Participant_Webform')
      ->execute()
      ->first();

    $this->_from = "
         FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
               INNER JOIN civicrm_participant {$this->_aliases['civicrm_participant']} ON {$this->_aliases['civicrm_participant']}.contact_id = {$this->_aliases['civicrm_contact']}.id
               INNER JOIN {$participantTransactionIDField['custom_group_id.table_name']} pv ON pv.entity_id = {$this->_aliases['civicrm_participant']}.id
               INNER JOIN civicrm_contribution {$this->_aliases['civicrm_contribution']} ON {$this->_aliases['civicrm_contribution']}.id = pv.{$participantTransactionIDField['column_name']}
               INNER JOIN civicrm_event {$this->_aliases['civicrm_event']} ON {$this->_aliases['civicrm_event']}.id = {$this->_aliases['civicrm_participant']}.event_id
               INNER JOIN {$this->_temporaryTableName} temp ON temp.transaction_id = {$this->_aliases['civicrm_contribution']}.id
               LEFT JOIN civicrm_option_value event_type_value ON event_type_value.value = {$this->_aliases['civicrm_event']}.event_type_id AND event_type_value.option_group_id = 15
               LEFT JOIN civicrm_value_cilb_exam_cat_6 exam_cat ON exam_cat.entity_id = event_type_value.id
               LEFT JOIN civicrm_loc_block clb ON clb.id = {$this->_aliases['civicrm_event']}.loc_block_id
               LEFT JOIN civicrm_address lba ON lba.id = clb.address_id
               LEFT JOIN civicrm_phone AS home ON home.contact_id = {$this->_aliases['civicrm_contact']}.id AND home.location_type_id = {$homeLocationId}
               LEFT JOIN civicrm_phone AS work ON work.contact_id = {$this->_aliases['civicrm_contact']}.id AND work.location_type_id IN ({$workLocationId}, {$mainLocationId})
               LEFT JOIN civicrm_option_value suffix_ov ON suffix_ov.value = {$this->_aliases['civicrm_contact']}.suffix_id AND suffix_ov.option_group_id = 7
               LEFT JOIN civicrm_option_value gender_ov ON gender_ov.value = {$this->_aliases['civicrm_contact']}.gender_id AND gender_ov.option_group_id = 3
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
      if ($key === 'civicrm_value_registrant_in_1_custom_2') {
        $this->_columnHeaders[$key]['title'] = E::ts('Exempt');
        $this->_columnHeaders[$key]['type'] = CRM_Utils_Type::T_STRING;
      }
      if ($key === 'civicrm_value_cilb_candidat_7_custom_31') {
        $this->_columnHeaders[$key]['title'] = E::ts('Entity ID');
      }
      if ($key === 'civicrm_value_candidate_res_9_custom_76') {
        $this->_columnHeaders[$key]['title'] = E::ts('Cand #');
      }
      if ($key === 'civicrm_value_candidate_res_9_custom_89') {
        $this->_columnHeaders[$key]['title'] = E::ts('Language');
      }
      if ($key === 'civicrm_value_registrant_in_1_custom_5') {
        $this->_columnHeaders[$key]['title'] = E::ts('SSN');
      }
      if ($key === 'civicrm_value_candidate_res_9_custom_80') {
        $this->_columnHeaders[$key]['title'] = E::ts('Exam Date');
      }
    }
    $fixedHeaders = [];
    $headerOrder = [
      'civicrm_participant_test_site',
      //'civicrm_value_candidate_res_9_custom_80',
      'civicrm_event_start_date',
      'civicrm_value_registrant_in_1_custom_5',
      'civicrm_contact_last_name',
      'civicrm_contact_first_name',
      'civicrm_contact_middle_name',
      'civicrm_contact_suffix_id',
      'civicrm_value_candidate_res_9_custom_76',
      'civicrm_participant_part1',
      'civicrm_participant_part2',
      'civicrm_participant_part3',
      'civicrm_address_street_address',
      'civicrm_address_supplemental_address_1',
      'civicrm_address_city',
      'civicrm_address_state_province_id',
      'civicrm_address_postal_code',
      'civicrm_phone_phone_home',
      'civicrm_phone_phone_work',
      'civicrm_contact_birth_date',
      'civicrm_value_candidate_res_9_custom_89',
      'civicrm_contact_gender_id',
      //'civicrm_contact_race',
      'civicrm_value_registrant_in_1_custom_97',
      'civicrm_email_email',
      'civicrm_contribution_receive_date',
      'civicrm_contribution_trxn_id',
      'civicrm_event_event_type_id',
      'civicrm_value_cilb_candidat_7_custom_25',
      'civicrm_contact_external_identifier',
      'civicrm_value_cilb_candidat_7_custom_31',
      'civicrm_participant_change_type',
      'civicrm_participant_deleted',
      'civicrm_participant_exam_date_change',
      'civicrm_participant_exam_event_change',
      'civicrm_participant_exam_part_change',
      'civicrm_participant_candidate_number_change',
      'civicrm_participant_category_change',
      'civicrm_value_registrant_in_1_custom_2',
    ];
    $originalColumnHeaders = $this->_columnHeaders;
    unset($originalColumnHeaders['civicrm_value_candidate_res_9_custom_96']);
    unset($originalColumnHeaders['civicrm_contact_race']);
    foreach ($headerOrder as $header) {
      $fixedHeaders[$header] = $originalColumnHeaders[$header];
      unset($originalColumnHeaders[$header]);
    }
    foreach (array_keys($originalColumnHeaders) as $header) {
      $fixedHeaders[$header] = $originalColumnHeaders[$header];
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

      $event_type_id = $row['civicrm_event_event_type_id'];
      $exam_code = $row['civicrm_value_cilb_candidat_7_custom_25'];
      if (!empty($row['civicrm_value_candidate_res_9_custom_96']) && ($event_type_id == CRM_Core_PseudoConstant::getKey('CRM_Event_BAO_Event', 'event_type_id', 'Business and Finance') ||
        $event_type_id == CRM_Core_PseudoConstant::getKey('CRM_Event_BAO_Event', 'event_type_id', 'Pool & Spa Servicing Business and Finance'))) {
        $event_type_id = trim($row['civicrm_value_candidate_res_9_custom_96'], CRM_Core_DAO::VALUE_SEPARATOR);
        $code_column_name = CRM_Core_DAO::singleValueQuery('SELECT column_name FROM civicrm_custom_field WHERE id = 16');
        $code_table_name = CRM_Core_DAO::singleValueQuery("SELECT table_name FROM civicrm_custom_group WHERE id = 6");
        $exam_code = CRM_Core_DAO::singleValueQuery("SELECT cv.{$code_column_name} FROM {$code_table_name} AS cv INNER JOIN civicrm_option_value ov ON ov.id = cv.entity_id WHERE ov.value = %1 and ov.option_group_id = 15", [
          1 => [$event_type_id, 'Integer'],
        ]);
      }

      $examPartCustomFieldsDetails = CustomField::get(FALSE)
        ->addSelect('custom_group_id.table_name', 'column_name')
        ->addWhere('name', '=', 'Exam_Part')
        ->addWhere('custom_group_id.name', '=', 'Exam_Details')
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
        INNER JOIN {$participantTransactionIDField['custom_group_id.table_name']} as ptf ON ptf.entity_id = cp.id
        INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = cp.event_id
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
          $paperCheck = CRM_Core_DAO::singleValueQuery("SELECT cv.{$examFormatCustomFieldsDetails['column_name']} as exam_format
            FROM civicrm_participant cp
            INNER JOIN {$participantTransactionIDField['custom_group_id.table_name']} as ptf ON ptf.entity_id = cp.id
            INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = cp.event_id
            WHERE ptf.{$participantTransactionIDField['column_name']} = %1
	          AND cv.{$examPartCustomFieldsDetails['column_name']} = 'TK'", [
            1 => [$row['civicrm_contribution_id'], 'Positive'],
          ]);
          if (empty($part1)) {
            if ($paperCheck == 'paper') {
              $part1 = 'TK';
            }
            else {
              $part1 = 'TK(CBT)';
            }
          }
          else {
            if ($paperCheck == 'paper') {
              $part2 = 'TK';
            }
            else {
              $part2 = 'TK(CBT)';
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
          elseif (!empty($part2)) {
            $part3 = 'PM(CBT)';
          }
          elseif (!empty($part1)) {
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
      // If the Event type is not Plumbing
      if ($event_type_id != CRM_Core_PseudoConstant::getKey('CRM_Event_BAO_Event', 'event_type_id', 'Plumbing')) {
        $rows[$rowNum]['civicrm_event_start_date'] = $rows[$rowNum]['civicrm_participant_test_site'] = '';
      }
      else {
        // If we do not have a trade knowledge plumbing exam blank out the fields
        if (!($part1 === 'TK' || $part2 === 'TK')) {
          $rows[$rowNum]['civicrm_event_start_date'] = $rows[$rowNum]['civicrm_participant_test_site'] = '';
        }
        else {
          $rows[$rowNum]['civicrm_event_start_date'] = date('m/d/Y', strtotime($row['civicrm_event_start_date']));
        }
      }
      //$rows[$rowNum]['civicrm_contact_gender'] = $rows[$rowNum]['civicrm_contact_race'] = '';
      $rows[$rowNum]['civicrm_value_registrant_in_1_custom_2'] = (!empty($row['civicrm_value_registrant_in_1_custom_2']) ? 'TRUE' : 'FALSE');
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

      if (!empty($event_type_id)) {
        $rows[$rowNum]['civicrm_event_event_type_id'] = $eventTypes[$event_type_id];
        $rows[$rowNum]['civicrm_value_cilb_candidat_7_custom_25'] = $exam_code;
        $entryFound = TRUE;
      }


      $change_columns = [
        'civicrm_participant_exam_date_change',
        'civicrm_participant_exam_part_change',
        'civicrm_participant_candidate_number_change',
        'civicrm_participant_category_change',
        'civicrm_participant_exam_event_change',
      ];
      foreach ($change_columns as $change_column) {
        if (array_key_exists($change_column, $row) && $rows[$rowNum][$change_column]) {
          $rows[$rowNum][$change_column] = 'Y';
          $entryFound = TRUE;
        }
      }

      if (array_key_exists('civicrm_participant_deleted', $row) && $rows[$rowNum]['civicrm_participant_deleted']) {
        $rows[$rowNum]['civicrm_participant_deleted'] = 'Yes';
        $entryFound = TRUE;
      }
      else {
        $rows[$rowNum]['civicrm_participant_deleted'] = '';
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_state_province_id', $row) && $rows[$rowNum]['civicrm_address_state_province_id']) {
        if (!array_key_exists($rows[$rowNum]['civicrm_address_state_province_id'], $stateProvinceIDs)) {
          $stateProvinceIDs[$rows[$rowNum]['civicrm_address_state_province_id']] = CRM_Core_DAO::singleValueQuery("SELECT abbreviation FROM civicrm_state_province WHERE id = %1", [1 => [$rows[$rowNum]['civicrm_address_state_province_id'], 'Positive']]);
        }
        $rows[$rowNum]['civicrm_address_state_province_id'] = $stateProvinceIDs[$rows[$rowNum]['civicrm_address_state_province_id']];
        $entryFound = TRUE;
      }

      if (!empty($exam_code)) {
        $rows[$rowNum]['civicrm_value_cilb_candidat_7_custom_31'] = CRM_Core_DAO::singleValueQuery("SELECT entity_id_imported__31 FROM civicrm_value_cilb_candidat_7 WHERE entity_id = %1 AND class_code_18 = %2 LIMIT 1", [
          1 => [$row['civicrm_contact_id'], 'Positive'],
          2 => [$exam_code, 'String'],
        ]);
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
      ->addWhere('name', '=', 'Exam_Part')
      ->addWhere('custom_group_id.name', '=', 'Exam_Details')
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
    $temporaryTableName = $this->createTemporaryTable('changed_records_table','id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, transaction_id int unsigned NOT NULL, exam_date_change int DEFAULT NULL, exam_event_change int DEFAULT NULL, exam_part_change int default NULL, candidate_number_change int DEFAULT NULL, category_change int default NULL, change_type varchar(255) DEFAULT NULL, deleted int NOT NULL default 0', TRUE);
    $sql = "ALTER TABLE {$temporaryTableName} ADD UNIQUE index ui_transaction_id(transaction_id)";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find all New transactions
    $sql = "INSERT INTO {$temporaryTableName}(transaction_id, change_type) SELECT c.id, 'Added'
      FROM civicrm_contribution c
      INNER JOIN `{$loggingDb}`.log_civicrm_contribution lc ON lc.id = c.id AND lc.log_date >= '{$lastRunCron}' AND lc.log_action = 'Insert'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find all deleted transactions
    $sql = "INSERT INTO {$temporaryTableName}(transaction_id, deleted) SELECT id, 1
      FROM `{$loggingDb}`.log_civicrm_contribution lc
      WHERE lc.log_date >= '{$lastRunCron}' AND lc.log_action = 'Delete'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql);
    // Find where exam date has changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_date_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN {$participantTransactionIDField['custom_group_id.table_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN civicrm_participant p ON p.id = ptf.entity_id
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN `{$loggingDb}`.log_civicrm_event le ON le.id = e.id WHERE le.log_date >= '{$lastRunCron}' AND le.log_action = 'Update' AND (le.start_date != e.start_date OR le.end_date != e.end_date) AND le.id = e.id
      ON DUPLICATE KEY UPDATE exam_date_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find where the exam has changed but the exam part is still the same
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_event_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN {$participantTransactionIDField['custom_group_id.table_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN civicrm_participant p ON p.id = ptf.entity_id
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id != e.id AND cv.{$examPartCustomFieldsDetails['column_name']} = lcv.{$examPartCustomFieldsDetails['column_name']}
      ON DUPLICATE KEY UPDATE exam_event_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find where the exam has changed and the exam part has changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_part_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN {$participantTransactionIDField['custom_group_id.table_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN civicrm_participant p ON p.id = ptf.entity_id
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id != e.id AND cv.{$examPartCustomFieldsDetails['column_name']} != lcv.{$examPartCustomFieldsDetails['column_name']}
      ON DUPLICATE KEY UPDATE exam_part_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find cases where an examp part has been removed and flag as exam part changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, exam_part_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN `{$loggingDb}`.log_{$participantTransactionIDField['custom_group_id.table_name']} as ptf ON ptf.{$participantTransactionIDField['column_name']} = c.id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant p ON p.id = ptf.entity_id
      WHERE ptf.log_date >= '{$lastRunCron}' AND ptf.log_action = 'Delete'
      ON DUPLICATE KEY UPDATE exam_part_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find where the exam has changed and the Category has changed
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, category_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN `{$loggingDb}`.log_civicrm_event lce ON lce.id = lcp.event_id
      INNER JOIN `{$loggingDb}`.log_{$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id != e.id AND lce.event_type_id != e.event_type_id
      ON DUPLICATE KEY UPDATE category_change=1,change_type='Change'
    ";
    $this->addToDeveloperTab($sql);
    CRM_Core_DAO::executeQuery($sql, [], TRUE, NULL, FALSE, FALSE);
    // Find where the candidate number has changed and we have the same exam.
    $sql = "INSERT INTO {$temporaryTableName} (transaction_id, candidate_number_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN {$participantCustomFieldDetails['custom_group_id.table_name']} as pcv On pcv.entity_id = p.id
      INNER JOIN `{$loggingDb}`.log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN `{$loggingDb}`.log_civicrm_event lce On lce.id = lcp.event_id
      INNER JOIN `{$loggingDb}`.log_{$participantCustomFieldDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'Update' AND lcp.event_id = e.id AND lce.event_type_id = e.event_type_id AND pcv.{$participantCustomFieldDetails['column_name']} != lcv.{$participantCustomFieldDetails['column_name']}
      ON DUPLICATE KEY UPDATE candidate_number_change=1,change_type='Change'
    ";
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
  public function select() {
    parent::select();
    $this->_select .= ', exam_cat.dbpr_code_3';
  }

}
