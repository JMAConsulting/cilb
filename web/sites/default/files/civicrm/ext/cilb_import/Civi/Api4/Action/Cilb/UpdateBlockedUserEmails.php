<?php

namespace Civi\Api4\Action\Cilb;

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
   * pti_candidates - so we couldn't match to get candidate emails
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
        pti_restricted_candidates.SSN,
        pti_restricted_candidates.Restriction_Reason,
        pti_candidates.Email,
        pti_candidates.FK_Account_ID
      FROM
        pti_restricted_candidates
      INNER JOIN
        pti_candidates
      ON
        pti_candidates.SSN = pti_restricted_candidates.SSN
    ") as $blocked) {

      // next check by SSN. based on review this would be a contact
      // created during the previous BlockedUsers import
      $findContact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('Registrant_Info.SSN', '=', $blocked['SSN'])
        ->execute();

      if (count($findContact) > 1) {
        // unexpected, log and skip
        $contactIds = implode(', ', $findContact->column('id'));
        Civi::log()->warning("Could not determine unique contact for SSN {$blocked['SSN']} - found {$contactIds}");
        echo "Could not determine unique contact for SSN {$blocked['SSN']} - found {$contactIds}";
        continue;
      }

      $contact = $findContact->first();

      if (!$contact) {
        Civi::log()->warning("Could not find ANY contact for SSN {$blocked['SSN']}");
        echo "Could not find ANY contact for SSN {$blocked['SSN']}";
        continue;
      }

      // find the previously created user
      $ufMatch = \Civi\Api4\UFMatch::get(FALSE)
        ->addWhere('contact_id', '=', $contact['id'])
        ->execute()->first();

      if ($ufMatch) {
        $oldUserId = $ufMatch['uf_id'];

        $oldUser = \Drupal\user\Entity\User::load($oldUserId);
        if (!$oldUser) {
          \Civi::log()->warning("No existing CMS user could be created for found for {$blocked['SSN']}");
        }
        $oldUser->delete();
      }

      // now check for an existing contact with this FK_Account_ID
      // this should only happen if this was created in ImportActivities
      // (because all the restricted candidate matches are from before
      // the 2019 cut off date )
      $activityContact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $blocked['FK_Account_ID'])
        ->execute()->first();

      if ($activityContact) {
        // we will use the activity contact as it has
        // the correct external ID

        // add restriction info to activity contact
        \Civi\Api4\Contact::update(FALSE)
          ->addWhere('id', '=', $activityContact['id'])
          ->addValue('Registrant_Info.Is_Restricted', TRUE)
          ->addValue('Registrant_Info.Restriction_Reason', $blocked['Restriction_Reason'] ?? NULL)
          ->addValue('Registrant_Info.SSN', $blocked['SSN'] ?? NULL)
          ->addValue('email', $blocked['Email'])
          ->execute();

        // delete the previous user contact
        \Civi\Api4\Contact::delete(FALSE)
          ->addWhere('id', '=', $contact['id'])
          ->execute();

        $contact = $activityContact;
      }

      $userParams = [
        'cms_name' => $blocked['Email'],
        'contactId' => $contact['id'],
        'email' => $blocked['Email'],
      ];

      $cmsUserId = \CRM_Core_BAO_CMSUser::create($userParams, 'email');

      $user = \Drupal\user\Entity\User::load($cmsUserId);
      if (!$user) {
        \Civi::log()->warning("No CMS user could be created for contact id {$contact['id']} email {$blocked['Email']}");
        continue;
      }
      $user->block();
      $user->save();
    }
  }

  protected static function createNewContact(array $blocked) {
    $candidateRecords = $this->getRows("SELECT FK_Account_ID, Email FROM pti_candidates WHERE SSN = '{$blocked['SSN']}'");
    $emails = array_map(fn ($record) => $record['Email'], $candidateRecords);

    if (count($emails) > 1) {
      $emails = implode(', ', $emails);
      Civi::log()->warning("Found multiple candidate emails for SSN {$blocked['SSN']} - found {$emails}");
    }

    // use the first (hopefully unique) email - or else create a pseudo email
    $email = $emails[0] ?? preg_replace('/[^A-Za-z]/', '', $blocked['Candidate_Name']) . '@blocked.local';

    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('display_name', $blocked['Candidate_Name'])
      ->addValue('email', $email)
      ->addValue('Registrant_Info.SSN', $blocked['SSN'] ?? NULL)
      ->execute()->single();

    return [
      'cms_name' => $email,
      'contactId' => $contact['id'],
      'email' => $email,
    ];
  }

}
