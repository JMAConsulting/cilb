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
    foreach ($this->getRows("SELECT SSN, Restriction_Reason, Candidate_Name FROM pti_Restricted_Candidates") as $blocked) {
      $contact = \Civi\Api4\Contact::create(FALSE)
        ->addValue('display_name', $blocked['Candidate_Name'])
        ->addValue('Registrant_Info.SSN', $blocked['SSN'] ?? NULL)
        ->addValue('Registrant_Info.Restriction_Reason', $blocked['Restriction_Reason'] ?? NULL)
        ->addValue('Registrant_Info.Is_Restricted', TRUE)
        ->execute()->single();

      $pseudoEmail = preg_replace('/[^A-Za-z]/', '', $blocked['Candidate_Name']) . '@blocked.local';

      $params = [
        'cms_name' => $pseudoEmail,
        'contactId' => $contact['id'],
        'email' => $pseudoEmail,
      ];

      $cmsUserId = \CRM_Core_BAO_CMSUser::create($params, 'email');

      $user = \Drupal\user\Entity\User::load($cmsUserId);
      if (!$user) {
        \Civi::log()->warning("No CMS user could be created for contact id {$contact['id']} email {$pseudoEmail}");
        continue;
      }
      $user->block();
      $user->save();
    }
  }

}