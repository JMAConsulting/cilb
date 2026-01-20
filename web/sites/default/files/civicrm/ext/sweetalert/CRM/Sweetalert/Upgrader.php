<?php
use CRM_Sweetalert_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Sweetalert_Upgrader extends \CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).


  public function postInstall() {
    self::postUpgrade();
  }

  public static function postUpgrade() {
    // Load the conditions/actions when extension is upgraded.
    if (!empty(Extension::get(FALSE)
      ->addWhere('file', '=', 'civirules')
      ->addWhere('status:name', '=', 'installed')
      ->execute()
      ->first())) {
      CRM_Civirules_Utils_Upgrader::insertActionsFromJson(E::path('civirules/actions.json'));
    }
    return TRUE;
  }

}
