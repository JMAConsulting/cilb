<?php
use CRM_CilbReports_ExtensionUtil as E;

class CRM_CilbReports_Form_Report_GusReport extends CRM_Report_Form_Event_ParticipantListing {
  function __construct() {
    parent::__construct();
    $this->_columns['civicrm_contact']['fields']['gus_dbpr_code'] = [
      'title' => E::ts('Gus DBPR Code'),
      'dbAlias' => 'gc.gus_dbpr_code_7',
    ];
    $this->_columns['civicrm_contact']['fields']['sort_name'] = [
      'title' => E::ts('Candidate Name'),
    //  'dbAlias' => 'CONCAT(contact_civireport.first_name, " ", contact_civireport.middle_name, " ", contact_civireport.last_name, " ", contact_civireport.suffix_id)',
        'dbAlias' => 'CONCAT(contact_civireport.first_name, " ", COALESCE(contact_civireport.middle_name, ""), " ", COALESCE(contact_civireport.last_name, ""))',
    ];
    $this->_columns['civicrm_contact']['fields']['gus_code'] = [ 
      'title' => E::ts('Gus Code'),
      'dbAlias' => 'gc.gus_code_6',
    ];
    $this->_columns['civicrm_address']['fields']['candidate_state'] = [
      'title' => E::ts('County Code'),
      'dbAlias' => '(CASE address_civireport.state_province_id
        WHEN "1008"
        THEN ccc.county_code ELSE 79 end
      )',
    ];
    unset($this->_columns['civicrm_contact']['fields']['sort_name_linked']);
  //  unset($this->_columns['civicrm_contact']['fields']['sort_name']);
    //CRM_Core_Error::debug_var('_columns', $this->_columns);
  }

  function preProcess() {
    $this->assign('reportTitle', E::ts('Membership Detail Report'));
    parent::preProcess();
  }

  function from() {
    parent::from();
    $this->_from .= "
LEFT JOIN civicrm_option_value et ON et.value = event_civireport.event_type_id AND option_group_id = 15
LEFT JOIN civicrm_value_cilb_exam_cat_6 gc ON gc.entity_id = et.id

LEFT JOIN civicrm_zip_county czp ON czp.zip_code = address_civireport.postal_code
LEFT JOIN civicrm_county_code ccc ON czp.county_id = ccc.id
";
  }

  function alterDisplay(&$rows) {
CRM_Core_Error::debug_var('rows', $rows);
return;
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

}
