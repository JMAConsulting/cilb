<?php

namespace Civi\Api4\Action\Cilb;

use Mpdf\Tag\P;

/**
 * run with cv api4 on the command line
 *
 * e.g.
 * cv api4 Cilb.import sourceDsn=[] \
 *  cutOffDate=2019-09-01 \
 *  recordLimit=100
 */
class UpdateBlockedUserEmails extends ImportBase {

  /**
   * The initial version of the import db had masked the SSNs in
   * pti_Candidates - so we couldn't match to get candidate emails
   *
   * This action checks for restricted_candidates we can now match
   * and:
   * - removes the previously created Drupal users
   * - adds the matched email and FK_Account_ID to the previously
   *   imported contact
   * - recreates a new blocked user with the new email
   *
   * Note: all the matching restricted_candidates are from BEFORE
   * the cut-off date 5 year look back period, so they should not
   * overlap with candidates imported through ImportCandidates
   *
   */
  protected function import() {
    foreach ($this->getRows("
      SELECT
        pti_Restricted_Candidates.SSN AS Candidate_SSN,
        pti_Restricted_Candidates.Restriction_Reason,
        pti_Restricted_Candidates.Candidate_Name,
        pti_Candidates.Email,
        pti_Candidates.FK_Account_ID,
        pti_Candidates.SSN
      FROM
        pti_Restricted_Candidates
      LEFT JOIN
        pti_Candidates
      ON
        pti_Candidates.SSN = pti_Restricted_Candidates.SSN
    ") as $blocked) {
      $contact = [];

      // next check by SSN. based on review this would be a contact
      // created during the previous BlockedUsers import
      if (!empty($blocked['SSN'])) {
        $findContact = \Civi\Api4\Contact::get(FALSE)
          ->addWhere('Registrant_Info.SSN', '=', $blocked['SSN'])
          ->execute();
      }
      else {
        // We are in a situation where we have a restricted candidate without a matching SSN. Try to match with first/last name.

        // Check to see first if the name has a comma.
        if (str_contains($blocked['Candidate_Name'], ',')) {
          [$lastName, $firstName] = array_map('trim', explode(',', $blocked['Candidate_Name'], 2));
        }
        else {
          [$firstName, $lastName] = array_map('trim', explode(' ', $blocked['Candidate_Name'], 2));
        }
	      foreach ($this->getRows("
          SELECT PK_Account_ID, Account_Name, SSN
          FROM System_Accounts
          LEFT JOIN pti_Candidates
          ON pti_Candidates.FK_Account_ID = System_Accounts.PK_Account_ID
          WHERE Last_Name LIKE ?
          AND First_Name LIKE ?
        ", [
        "%{$lastName}%",
        "%{$firstName}%"
        ]) as $matchingContact) {
          if (!empty($matchingContact['PK_Account_ID'])) {
            $findContact = \Civi\Api4\Contact::get(FALSE)
              ->addWhere('external_identifier', '=', $matchingContact['PK_Account_ID'])
              ->execute();
            if (count($findContact) == 1) {
              // found a unique match
              $blocked['Email'] = $matchingContact['Account_Name'];
              $blocked['SSN'] = $matchingContact['SSN'];
              break;
            }
          }
        }
      }
      if (empty($blocked['Email'])) {
        // create a dummy email to use for blocked user with lowercase first/last name without spaces
        $firstNameClean = strtolower(preg_replace('/\s+/', '', explode(',', $blocked['Candidate_Name'])[1] ?? 'blocked'));
        $lastNameClean = strtolower(preg_replace('/\s+/', '', explode(',', $blocked['Candidate_Name'])[0] ?? 'user'));
        $blocked['Email'] = $firstNameClean.$lastNameClean.'@blocked.local';
      }

      if (!empty($findContact)) {
        $contact = $findContact->first();
      }

      if (empty($contact)) {
        // no contact found - create a new one
        $contact = $this->createNewContact($blocked);
      }

      if ($contact) {
        // add restriction info to activity contact
        \Civi\Api4\Contact::update(FALSE)
          ->addWhere('id', '=', $contact['id'])
          ->addValue('Registrant_Info.Is_Restricted', [TRUE])
          ->addValue('Registrant_Info.Restriction_Reason', $blocked['Restriction_Reason'] ?? NULL)
          ->addValue('Registrant_Info.SSN', $blocked['SSN'] ?? NULL)
          ->addValue('email', $blocked['Email'])
          ->execute();
      }

      $userParams = [
        'cms_name' => $blocked['Email'],
        'contactId' => $contact['id'],
        'email' => $blocked['Email'],
      ];

      $user = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['mail' => $blocked['Email']]);
      if (empty($user)) {
        $cmsUserId = \CRM_Core_BAO_CMSUser::create($userParams, 'email');
      }
      else {
	      $user = reset($user);
	      $cmsUserId = $user->id();
      }
      $user = \Drupal\user\Entity\User::load($cmsUserId);
      if (!$user) {
        $this->warning("No CMS user could be created for contact id {$contact['id']} email {$blocked['Email']}");
        continue;
      }
      $user->block();
      $user->save();

      // Create the activity log entry as well.
      if (!empty($blocked['FK_Account_ID'])) {
	      $this->importActivityLog($contact['id'], $blocked);
      }
    }
  }

  protected function importActivityLog($contactId, $blocked) {
    foreach ($this->getRows("SELECT PK_Activity_Type, Activity_Type FROM pti_Code_Activity_Type") as $activityType) {
      $activityTypes[$activityType['PK_Activity_Type']] = $activityType['Activity_Type'];
    }
    foreach ($this->getRows("SELECT
         PK_Activity_Log_ID,
         FK_Account_ID,
         FK_Candidate_ID,
         Created_By,
         Created_Date,
         Description,
         FK_Activity_Log_Type_ID
      FROM pti_Activity_Log
      WHERE FK_Account_ID = {$blocked['FK_Account_ID']}
    ") as $activity) {
      $sourceContactId = self::getOrCreateContact($activity['Created_By']);

      $targetContactIds = [$contactId];

      $activityTypeName = $activityTypes[$activity['FK_Activity_Log_Type_ID']] ?? NULL;

      if (!$activityTypeName) {
        // activity type not found - something's gone wrong
        throw new \CRM_Core_Exception("Couldn't find imported activity type for code {$activity['FK_Activity_Log_Type_ID']} when importing activity {$activity['PK_Activity_Log_ID']}. Something's wrong :/");
      }

      $subject = explode('.', $activity['Description'])[0] ?: $activityTypeName;

      $activity = \Civi\Api4\Activity::create(FALSE)
        ->addValue('source_contact_id', $contactId)
        ->addValue('source_record_id', $activity['PK_Activity_Log_ID'])
        ->addValue('target_contact_id', $targetContactIds)
        ->addValue('activity_date_time', $activity['Created_Date'])
        ->addValue('details', $activity['Description'])
        ->addValue('activity_type_id:name', $activityTypeName)
          // put the activity type in the subject as well cause otherwise it's empty
        ->addValue('subject', $subject)
        ->execute()->first();
    }
  }

  protected function createNewContact(array $blocked) {
    // Check to see first if the name has a comma.
    if (str_contains($blocked['Candidate_Name'], ',')) {
      [$lastName, $firstName] = array_map('trim', explode(',', $blocked['Candidate_Name'], 2));
    }
    else {
      [$firstName, $lastName] = array_map('trim', explode(' ', $blocked['Candidate_Name'], 2));
    }
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('first_name', $firstName)
      ->addValue('last_name', $lastName)
      ->addValue('email', $blocked['Email'])
      ->addValue('Registrant_Info.SSN', $blocked['SSN'] ?? NULL)
      ->execute()->single();

    return $contact;
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
