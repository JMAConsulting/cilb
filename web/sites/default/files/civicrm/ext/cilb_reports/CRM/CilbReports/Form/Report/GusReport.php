<?php
use CRM_CilbReports_ExtensionUtil as E;
use \Civi\Api4\CustomField;
use \Civi\Api4\CustomGroup;
use \Civi\Api4\OptionGroup;

class CRM_CilbReports_Form_Report_GusReport extends CRM_Report_Form_Event_ParticipantListing {
  function __construct() {
    parent::__construct();
    $dbprCF = CustomField::get(FALSE)
      ->addSelect('column_name')
      ->addWhere('name', '=', 'Gus_DBPR_Code')
      ->execute()
      ->first()['column_name'];
    $gusCodeCF = CustomField::get(FALSE)
      ->addSelect('column_name')
      ->addWhere('name', '=', 'Gus_Code')
      ->execute()
      ->first()['column_name'];
    $this->_columns['civicrm_contact']['fields'] = [
      '6' => [
        'title' => '',
        'required' => TRUE,
        'dbAlias' => '"6"',
      ],
      'gus_dbpr_code' => [
        'title' => E::ts('Gus DBPR Code'),
        'dbAlias' => 'gc.' . $dbprCF,
      ],
    ] + $this->_columns['civicrm_contact']['fields'];
    $this->_columns['civicrm_contact']['fields']['sort_name'] = [
      'title' => E::ts('Candidate Name'),
        'dbAlias' => 'CONCAT(contact_civireport.first_name, " ", COALESCE(contact_civireport.middle_name, ""), " ", COALESCE(contact_civireport.last_name, ""))',
    ];
    $this->_columns['civicrm_contact']['fields']['gus_code'] = [
      'title' => E::ts('Gus Code'),
      'dbAlias' => 'gc.' . $gusCodeCF,
    ];
    $this->_columns['civicrm_address']['fields']['county_code'] = [
      'title' => E::ts('County Code'),
      'dbAlias' => '(CASE address_civireport.state_province_id
        WHEN "1008"
        THEN ccc.county_code ELSE 79 end
      )',
    ];
    $this->_columns['civicrm_contact']['fields']['suffix_id']['required'] = TRUE;
    $this->_columns['civicrm_contact']['fields']['suffix_id']['no_display'] = TRUE;
    $this->_columns['civicrm_value_candidate_res_9']['fields']['custom_80']['type'] = CRM_Utils_Type::T_DATE + CRM_Utils_Type::T_TIME;
    $this->_columns['civicrm_value_candidate_res_9']['fields']['custom_80']['title'] = ts('Exam Date');
    $this->_columns['civicrm_address']['fields']['address_supplemental_address_1']['title'] = ts('Supplementary Address');
    $this->_columns['civicrm_phone']['fields']['b_literal_text'] = ['title' => 'B', 'dbAlias' => '"B"'];
    $this->_columns['civicrm_contact']['fields']['blank_1'] = ['title' => '', 'dbAlias' => '""'];
    $this->_columns['civicrm_contact']['fields']['blank_2'] = ['title' => '', 'dbAlias' => '""'];
    unset($this->_columns['civicrm_contact']['fields']['sort_name_linked']);
  }

  function from() {
    parent::from();
    $examTypeTableName = CustomGroup::get(FALSE)
      ->addSelect('table_name')
      ->addWhere('name', '=', 'Exam_Type_Details')
      ->execute()
      ->first()['table_name'];
    $eventType = OptionGroup::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'event_type')
      ->execute()->first()['id'];
    $this->_from .= "
        LEFT JOIN civicrm_option_value et ON et.value = event_civireport.event_type_id AND option_group_id = $eventType
        LEFT JOIN $examTypeTableName gc ON gc.entity_id = et.id
        LEFT JOIN civicrm_zip_county czp ON czp.zip_code = address_civireport.postal_code
        LEFT JOIN civicrm_county_code ccc ON czp.county_id = ccc.id
    ";
  }

  function where() {
    parent::where();
    $this->_where .= " AND event_civireport.start_date >= CURDATE()";
  }

  function alterDisplay(&$rows) {
    $suffixes = CRM_Contact_BAO_Contact::buildOptions('suffix_id');
    foreach ($rows as $rowNum => $row) {
      if (!empty($row['civicrm_contact_suffix_id'])) {
        $rows[$rowNum]['civicrm_contact_sort_name'] .= ' ' . $suffixes[$row['civicrm_contact_suffix_id']];
      }
      if (!empty($row['civicrm_value_candidate_res_9_custom_80'])) {
        $rows[$rowNum]['civicrm_value_candidate_res_9_custom_80'] = str_replace('00:00:00', '0:00', $row['civicrm_value_candidate_res_9_custom_80']);
      }
    }
    $alteredColumnHeaders = [];
      foreach([
        'civicrm_contact_6',
        'civicrm_contact_gus_dbpr_code',
        'civicrm_contact_sort_name',
        'civicrm_contact_blank_1',
        'civicrm_contact_gus_code',
        'civicrm_address_address_street_address',
        'civicrm_address_address_supplemental_address_1',
        'civicrm_contact_blank_2',
        'civicrm_address_address_city',
        'civicrm_address_address_state_province_id',
        'civicrm_address_county_code',
        'civicrm_address_address_postal_code',
        'civicrm_phone_phone',
        'civicrm_phone_b_literal_text',
        'civicrm_value_candidate_res_9_custom_80',
        'civicrm_email_email',
      ] as $header) {
        $alteredColumnHeaders[$header] = $this->_columnHeaders[$header];
        if ($header == 'civicrm_value_candidate_res_9_custom_80') {
          $alteredColumnHeaders[$header]['type'] = 1;
        }
        unset($this->_columnHeaders[$header]);
      }
      $this->_columnHeaders = $alteredColumnHeaders + $this->_columnHeaders;
  }

}
