<?php
use CRM_CilbReports_ExtensionUtil as E;
use Civi\Api4\CustomField;

class CRM_CilbReports_Form_Report_MTFReport extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  private $_temporaryTableName = NULL;

  protected $_customGroupExtends = ['Participants', 'Contacts', 'Individuals'];
  protected $_customGroupGroupBy = FALSE;
  public function __construct() {
    $this->_columns = array(
      'civicrm_contact' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'first_name' => array(
            'title' => E::ts('First Name'),
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'last_name' => array(
            'title' => E::ts('Last Name'),
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'operator' => 'like',
          ),
          'id' => array(
            'no_display' => TRUE,
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_membership' => array(
        'dao' => 'CRM_Member_DAO_Membership',
        'fields' => array(
          'membership_type_id' => array(
            'title' => 'Membership Type',
            'required' => TRUE,
            'no_repeat' => TRUE,
          ),
          'join_date' => array(
            'title' => E::ts('Join Date'),
            'default' => TRUE,
          ),
          'source' => array('title' => 'Source'),
        ),
        'filters' => array(
          'join_date' => array(
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
          'owner_membership_id' => array(
            'title' => E::ts('Membership Owner ID'),
            'operatorType' => CRM_Report_Form::OP_INT,
          ),
          'tid' => array(
            'name' => 'membership_type_id',
            'title' => E::ts('Membership Types'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Member_PseudoConstant::membershipType(),
          ),
        ),
        'grouping' => 'member-fields',
      ),
      'civicrm_membership_status' => array(
        'dao' => 'CRM_Member_DAO_MembershipStatus',
        'alias' => 'mem_status',
        'fields' => array(
          'name' => array(
            'title' => E::ts('Status'),
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'sid' => array(
            'name' => 'id',
            'title' => E::ts('Status'),
            'type' => CRM_Utils_Type::T_INT,
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => CRM_Member_PseudoConstant::membershipStatus(NULL, NULL, 'label'),
          ),
        ),
        'grouping' => 'member-fields',
      ),
      'civicrm_address' => array(
        'dao' => 'CRM_Core_DAO_Address',
        'fields' => array(
          'street_address' => NULL,
          'city' => NULL,
          'postal_code' => NULL,
          'state_province_id' => array('title' => E::ts('State/Province')),
          'country_id' => array('title' => E::ts('Country')),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_email' => array(
        'dao' => 'CRM_Core_DAO_Email',
        'fields' => array('email' => NULL),
        'grouping' => 'contact-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;
    parent::__construct();
  }

  public function preProcess() {
    $this->assign('reportTitle', E::ts('Membership Detail Report'));
    parent::preProcess();
  }

  public function from() {
    $this->_temporaryTableName = $this->createChangeTemporaryTable();
    $this->_from = NULL;

    $this->_from = "
         FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}
               INNER JOIN civicrm_membership {$this->_aliases['civicrm_membership']}
                          ON {$this->_aliases['civicrm_contact']}.id =
                             {$this->_aliases['civicrm_membership']}.contact_id AND {$this->_aliases['civicrm_membership']}.is_test = 0
               LEFT  JOIN civicrm_membership_status {$this->_aliases['civicrm_membership_status']}
                          ON {$this->_aliases['civicrm_membership_status']}.id =
                             {$this->_aliases['civicrm_membership']}.status_id ";


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
    $checkList = array();
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

      if (array_key_exists('civicrm_membership_membership_type_id', $row)) {
        if ($value = $row['civicrm_membership_membership_type_id']) {
          $rows[$rowNum]['civicrm_membership_membership_type_id'] = CRM_Member_PseudoConstant::membershipType($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_state_province_id', $row)) {
        if ($value = $row['civicrm_address_state_province_id']) {
          $rows[$rowNum]['civicrm_address_state_province_id'] = CRM_Core_PseudoConstant::stateProvince($value, FALSE);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_address_country_id', $row)) {
        if ($value = $row['civicrm_address_country_id']) {
          $rows[$rowNum]['civicrm_address_country_id'] = CRM_Core_PseudoConstant::country($value, FALSE);
        }
        $entryFound = TRUE;
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

      if (!$entryFound) {
        break;
      }
    }
  }

  private function createChangeTemporaryTable(): string {
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
      ->addWhere('name', '=', 'Exam_Format')
      ->addWhere('custom_group_id.name', '=', 'Exam_Details')
      ->execute()
      ->first();
    $lastRunCron = Civi::settings()->get('cilb_report_mtfreport_last_run_date') ?? date('YmdHis', strtotime('-1 week'));
    $temporaryTableName = $this->createTemporaryTable('changed_records_table','id int unsigned NOT NULL AUTO_INCREMENT, transaction_id int unsigned NOT NULL, exam_date_change int DEFAULT NULL, exam_event_change int DEFAULT NULL, exam_part_change int default NULL, candidate_number_change int DEFAULT NULL, category_change int default NULL, change_type varchar(255) DEFAULT NULL, deleted int NOT NULL default 0, primary_key(id)');
    CRM_Core_DAO::executeQuery("ALTER TABLE {$temporaryTableName} ADD UNIQUE index ui_transaction_id(transaction_id)");
    // Find all New transactions
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName},(transaction_id, change_type) SELECT c.id, 'Added'
      FROM civicrm_contribution c
      INNER JOIN log_civicrm_contribution lc ON lc.id = c.id AND lc.log_date >= '{$lastRunCron}' AND le.log_action = 'Added'
    ");
    // Find all deleted transactions
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName},(transaction_id, deleted) SELECT id, 1
      FROM log_civicrm_contribution lc
      WHERE lc.log_date >= '{$lastRunCron}' AND le.log_action = 'Deleted'
    ");
    // Find where exam date has changed
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName} (transaction_id, exam_date_change, change_type) SELECT c.id, 1, 'Change'
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN log_civicrm_event le ON le.id = e.id WHERE le.log_date >= '{$lastRunCron}' AND le.log_action = 'Update' AND (le.start_date != e.start_date OR le.end_date != e.end_date) AND le.event_id = e.id
    ");
    // Find where the exam has changed but the exam part is still the same
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName} (transaction_id, exam_event_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'update' AND lcp.event_id != e.id AND cv.{$examPartCustomFieldsDetails['column_name']} = lcv.{$examPartCustomFieldsDetails['column_name']}
      ON DUPLICATE KEY UPDATE exam_event_change=1,change_type='Change'
    ");
    // Find where the exam has changed and the exam part has changed
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName} (transaction_id, exam_part_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id
      INNER JOIN log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'update' AND lcp.event_id != e.id AND cv.{$examPartCustomFieldsDetails['column_name']} != lcv.{$examPartCustomFieldsDetails['column_name']}
      ON DUPLICATE KEY UPDATE exam_part_change=1,change_type='Change'
    ");
    // Find cases where the line item has been removed and flag as exam part changed
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName} (transaction_id, exam_part_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN log_civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN log_civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN log_civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id
      WHERE cli.log_date >= '{$lastRunCron}' AND cli.log_action = 'delete'
      ON DUPLICATE KEY UPDATE exam_part_change=1,change_type='Change'
    ");
    // Find where the exam has changed and the Category has changed
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName} (transaction_id, category_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN log_civicrm_event lce On lce.id = lcp.event_id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'update' AND lcp.event_id != e.id AND lce.event_type_id != e.event_type_id
      ON DUPLICATE KEY UPDATE category_change=1,change_type='Change'
    ");
    // Find where the candidate number has changed
    CRM_Core_DAO::executeQuery("INSERT INTO {$temporaryTableName} (transaction_id, category_change, change_type) SELECT c.id, 1, 'Change',
      FROM civicrm_contribution c
      INNER JOIN civicrm_line_item cli ON cli.contribution_id = c.id
      INNER JOIN civicrm_participant p ON p.id = cli.entity_id AND cli.entity_table = 'civicrm_participant'
      INNER JOIN civicrm_event e ON e.id = p.event_id
      INNER JOIN {$examFormatCustomFieldsDetails['custom_group_id.table_name']} as cv ON cv.entity_id = e.id AND cv.{$examFormatCustomFieldsDetails['column_name']} = 'paper'
      INNER JOIN log_civicrm_participant lcp ON lcp.id = p.id
      INNER JOIN log_civicrm_event lce On lce.id = lcp.event_id
      INNER JOIN {$examPartCustomFieldsDetails['custom_group_id.table_name']} as lcv ON lcv.entity_id = lcp.event_id
      WHERE lcp.log_date >= '{$lastRunCron}' AND lcp.log_action = 'update' AND lcp.event_id != e.id AND lce.event_type_id != e.event_type_id
      ON DUPLICATE KEY UPDATE category_change=1,change_type='Change'
    ");
    return $temporaryTableName;
  }

}
