<?php

class CRM_Advimport_Utils_Hook {

  /**
   * This hook is called to get a list of extensions implementing the
   * advimport features.
   *
   * @return mixed
   *   based on op. pre-hooks return a boolean or
   *   an error message which aborts the operation
   */
  public static function getAdvimportHelpers(&$classes) {
    $hook = CRM_Utils_Hook::singleton();
    return $hook->invoke(
      ['classes'],
      $classes,
      CRM_Utils_Hook::$_nullObject,
      CRM_Utils_Hook::$_nullObject,
      CRM_Utils_Hook::$_nullObject,
      CRM_Utils_Hook::$_nullObject,
      CRM_Utils_Hook::$_nullObject,
      'civicrm_advimport_helpers');
  }

}
