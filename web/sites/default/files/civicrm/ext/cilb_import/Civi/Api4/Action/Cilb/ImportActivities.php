<?php

namespace Civi\Api4\Action\Cilb;

/**
 * run with cv api4 on the command line
 *
 * e.g.
 * cv api4 Cilb.importActivities sourceDsn=[] \
 *  cutOffDate=2019-09-01 \
 *  recordLimit=100
 */
class ImportActivities extends ImportBase {

  /**
   * import activities from Activity Log table
   *
   * NOTE: we fetch the activity type codes into memory, there's only 5
   */
  protected function import() {
    $activityTypes = [];

    foreach ($this->getRows("SELECT PK_Activity_Type, Activity_Type FROM pti_code_activity_type") as $activityType) {
      $activityTypes[$activityType['PK_Activity_Type']] = $activityType['Activity_Type'];
    }

    // ensure the activity types exist in CiviCRM
    foreach ($activityTypes as $name) {
      \Civi\Api4\OptionValue::save(FALSE)
        ->addRecord([
          'name' => $name,
          'label' => $name,
          'option_group_id.name' => 'activity_type',
        ])
        ->setMatch(['name', 'option_group_id'])
        ->execute();
    }

    // now fetch and create the activities themselves
    foreach ($this->getRows("SELECT PK_Activity_Log_ID, FK_Account_ID, Created_Date, Description, FK_Activity_Log_Type_ID, Created_By FROM pti_activity_log WHERE Created_Date > '{$this->cutOffDate}'") as $activity) {

      $targetContact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $activity['FK_Account_ID'])
        ->addSelect('id')
        ->execute()
        ->first();

      if (!$targetContact) {
        // given we have only imported contacts updated in the last 5 years,
        // this query might find some activities created in the last 5 years
        // which link to even older contacts - so this might be fine
        // TODO: check OR limit with a JOIN?
        \Civi::log()->warning("Imported target contact not found for Activity {$activity['PK_Activity_Log_ID']}. Account ID was {$activity['FK_Account_ID']}");
      }

      $sourceContact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $activity['Created_By'])
        ->addSelect('id')
        ->execute()
        ->first();

      if (!$sourceContact) {
        // given we have only imported contacts updated in the last 5 years,
        // this query might find some activities created in the last 5 years
        // which link to even older contacts - so this might be fine
        \Civi::log()->warning("Imported source contact not found for Activity {$activity['PK_Activity_Log_ID']}. Account ID was {$activity['Created_By']}");
        continue;
      }

      $activityTypeName = $activityTypes[$activity['FK_Activity_Log_Type_ID']] ?? NULL;

      if (!$activityTypeName) {
        // activity type not found - something's gone wrong
        throw new \CRM_Core_Exception("Couldn't find imported activity type for code {$activity['FK_Activity_Log_Type_ID']} when importing activity {$activity['PK_Activity_Log_ID']}. Something's wrong :/");
      }

      \Civi\Api4\Activity::create(FALSE)
        ->addValue('target_contact_id', $targetContact['id'])
        ->addValue('source_contact_id', $sourceContact['id'])
        ->addValue('activity_date', $activity['Created_Date'])
        ->addValue('details', $activity['Description'])
        ->addValue('activity_type_id.name', $activityTypeName)
          // put the activity type in the subject as well cause otherwise it's empty
        ->addValue('subject', $activityTypeName)
        ->execute();
    }
  }

}