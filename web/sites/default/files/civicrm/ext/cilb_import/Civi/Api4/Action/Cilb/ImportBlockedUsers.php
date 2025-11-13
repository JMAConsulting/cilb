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
class ImportBlockedUsers extends ImportBase {

  /**
   * Import "restricted candidates" who have been blocked for some reason
   *
   * Note: there is nothing in the source DB that actually links these
   * with other records. They only have SSN and Candidate Name and the SSN
   * don't match any records in pti_Candidates
   *
   * We create contacts using these SSNs, then create a linked user and
   * set the status to blocked
   */
  protected function import() {
    foreach ($this->getRows("
      SELECT SSN, Restriction_Reason, Candidate_Name, Email, FK_Account_ID 
      FROM pti_Restricted_Candidates rc
      LEFT JOIN pti_Candidates c
      ON rc.SSN = c.SSN
      ") as $blocked) {
        if (empty($blocked['Email'])) {
          $email = preg_replace('/[^A-Za-z]/', '', $blocked['Candidate_Name']) . '@blocked.local';
        }
        else {
          $email = $blocked['Email'];
        }
        $contact = \Civi\Api4\Contact::save(FALSE)
          ->addRecord([
            'display_name' => $blocked['Candidate_Name'],
            'Registrant_Info.SSN' => $blocked['SSN'] ?? NULL,
            'Registrant_Info.Restriction_Reason' => $blocked['Restriction_Reason'] ?? NULL,
            'Registrant_Info.Is_Restricted' => TRUE,
            'contact_type' => 'Individual',
            'external_id' => $blocked['FK_Account_ID'] ?? NULL,
          ])
          ->setMatch(['external_id'])
          ->execute()->first();

        // Set the email address as well
        \Civi\Api4\Email::save(FALSE)
          ->addRecord([
            'email', $email,
            'contact_id' => $contact['id'],
          ])
          ->setMatch(['contact_id', 'email'])
          ->execute();

        $params = [
          'cms_name' => $email,
          'contactId' => $contact['id'],
          'email' => $email,
        ];

        $cmsUserId = \CRM_Core_BAO_CMSUser::create($params, 'email');

        $user = \Drupal\user\Entity\User::load($cmsUserId);
        if (!$user) {
          $this->warning("No CMS user could be created for contact id {$contact['id']} email {$email}");
          continue;
        }
        $user->block();
        $user->save();
    }
  }
}