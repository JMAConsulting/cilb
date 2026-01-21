<?php

class CRM_Cronplus_APIWrapper implements API_Wrapper {

  /**
   * the wrapper contains a method that allows you to alter the parameters of the api request (including the action and the entity)
   */
  public function fromApiInput($apiRequest) {
    $apiRequest['entity'] = 'cronplus';
    $apiRequest['action'] = 'execute';
    $apiRequest['function'] = '_civicrm_api3_cronplus_execute';
    return $apiRequest;
  }

  /**
   * alter the result before returning it to the caller.
   */
  public function toApiOutput($apiRequest, $result) {
    return $result;
  }

}
