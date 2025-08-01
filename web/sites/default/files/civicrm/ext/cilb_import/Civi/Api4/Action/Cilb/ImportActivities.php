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
   * @var string
   * @required
   *
   * 4 digit year to enable importing in segments
   */
  protected string $transactionYear;

  /**
   * import activities from Activity Log table
   *
   * NOTES:
   * - we fetch the activity type codes into memory, there's only 5
   * - we map FK_Candidate_ID to a target contact on the activity
   * - we map Created_By to source_contact_id
   * - in vast majority of cases FK_Account_ID matches created by, so we just ignore
   * - in the few where it doesn't, we include as an additional target contact
   * - we create additional blank contacts for Account IDs that haven't already been imported
   *
   *
   */
  protected function import() {
    $this->info("Importing activities for {$this->transactionYear}...");

    $activityTypes = [];

    foreach ($this->getRows("SELECT PK_Activity_Type, Activity_Type FROM pti_Code_Activity_Type") as $activityType) {
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
    foreach ($this->getRows("SELECT
         PK_Activity_Log_ID,
         FK_Account_ID,
         FK_Candidate_ID,
         Created_By,
         Created_Date,
         Description,
         FK_Activity_Log_Type_ID
      FROM pti_Activity_Log
      WHERE Created_Date > '{$this->cutOffDate}'
      AND YEAR(Created_Date) = '{$this->transactionYear}'
    ") as $activity) {

      $sourceContactId = self::getOrCreateContact($activity['Created_By']);

      $targetContactIds = [self::getOrCreateContact($activity['FK_Candidate_ID'])];

      if ($activity['FK_Account_ID'] !== $activity['Created_By']) {
        // this usually matches the source contact, but if it doesn't add an additional target contact
        $targetContactIds[] = self::getOrCreateContact($activity['FK_Candidate_ID']);
      }

      $activityTypeName = $activityTypes[$activity['FK_Activity_Log_Type_ID']] ?? NULL;

      if (!$activityTypeName) {
        // activity type not found - something's gone wrong
        throw new \CRM_Core_Exception("Couldn't find imported activity type for code {$activity['FK_Activity_Log_Type_ID']} when importing activity {$activity['PK_Activity_Log_ID']}. Something's wrong :/");
      }

      $subject = explode('.', $activity['Description'])[0] ?: $activityTypeName;

      $activity = \Civi\Api4\Activity::create(FALSE)
        ->addValue('source_contact_id', $sourceContactId)
        ->addValue('target_contact_id', $targetContactIds)
        ->addValue('activity_date_time', $activity['Created_Date'])
        ->addValue('details', $activity['Description'])
        ->addValue('activity_type_id:name', $activityTypeName)
          // put the activity type in the subject as well cause otherwise it's empty
        ->addValue('subject', $subject)
        ->execute()->first();
    }
  }

  protected static function getOrCreateContact($externalId) {
    $contact =
      \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $externalId)
        ->addSelect('id')
        ->execute()
        ->first()
      ?:
      \Civi\Api4\Contact::create(FALSE)
        ->addValue('external_identifier', $externalId)
        ->addValue('display_name', "[imported activity contact {$externalId}]")
        ->execute()
        ->first();

    return $contact['id'];
  }

}
