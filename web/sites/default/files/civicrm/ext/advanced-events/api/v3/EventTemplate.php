<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use CRM_AdvancedEvents_ExtensionUtil as E;

/**
 * EventTemplate.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws CRM_Core_Exception
 */
function civicrm_api3_event_template_create($params) {
  if (!empty($params['event_id']) && empty($params['template_id'])) {
    // We are creating an event template from an existing event
    $copy = CRM_Event_BAO_Event::copy($params['event_id']);
    $copy->is_template = 1;
    $copy->template_title = $copy->title . $copy->template_title;
    $copy->title = '';
    $copy->save();
    return civicrm_api3_create_success($copy->toArray());
  }
  else {
    return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
  }
}

/**
 * EventTemplate.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws CRM_Core_Exception
 */
function civicrm_api3_event_template_delete($params) {
  /*if (empty($params['id'])) {
    $eventTemplate = civicrm_api3('EventTemplate', 'getsingle', ['event_id' => $params['event_id']]);
    $params['id'] = $eventTemplate['id'];
  }*/
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

function _civicrm_api3_event_template_delete_spec(&$spec) {
  $spec['id']['api.required'] = 0;
}

/**
 * EventTemplate.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws CRM_Core_Exception
 */
function civicrm_api3_event_template_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}
