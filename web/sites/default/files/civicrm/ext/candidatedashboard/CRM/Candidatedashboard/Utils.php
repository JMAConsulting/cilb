<?php

class CRM_Candidatedashboard_Utils {

public static function updateSearchKitDisplay($oldValue, $newValue) {
  CRM_Core_Error::debug_var('oldValue', $oldValue);
  CRM_Core_Error::debug_var('newValue', $newValue);
  $activitySearch = \Civi\Api4\SavedSearch::get(FALSE)
  ->addWhere('name', '=', 'ActivitySearch')
  ->execute()->first();
  $apiParams = $activitySearch['api_params'];
  if (!empty($newValue)) {
    $apiParams['where'] = [
        [
          "is_deleted",
          "=",
          false
        ],
        [
          "activity_type_id",
          "NOT IN",
          $newValue
        ]
    ];
    CRM_Core_DAO::executeQuery("UPDATE civicrm_saved_search SET api_params='" . json_encode($apiParams) . "' WHERE id = " . $activitySearch['id']);
  }
}

}
