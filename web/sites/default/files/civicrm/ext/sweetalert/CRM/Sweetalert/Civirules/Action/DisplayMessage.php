<?php

class CRM_Sweetalert_Civirules_Action_DisplayMessage extends CRM_Civirules_Action {

  /**
   * Execute the action
   */
  public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
    $action_params = $this->getActionParameters();
    CRM_Sweetalert_Utils::setStatus($action_params['message'], $action_params['title'], $action_params['type']);
  }

  /**
   * Method to return the url for additional form processing for action
   * and return false if none is needed
   *
   * @param int $ruleActionId
   * @return bool
   */
  public function getExtraDataInputUrl($ruleActionId) {
    return $this->getFormattedExtraDataInputUrl('civicrm/civirule/form/action/sweetalert_message/', $ruleActionId);
  }

  /**   
   * Copy of CiviRules function which did not exist in CiviRules < 3.17
   * Remove eventually.
   *
   * @param string $url
   * @param int $ruleActionID
   *        
   * @return string
   */
  public function getFormattedExtraDataInputUrl(string $url, int $ruleActionID): string {
    return CRM_Utils_System::url($url, 'rule_action_id=' . $ruleActionID, FALSE, NULL, FALSE, FALSE, TRUE);
  }   

}
