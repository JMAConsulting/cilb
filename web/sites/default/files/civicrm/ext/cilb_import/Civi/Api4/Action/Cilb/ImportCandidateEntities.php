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
class ImportCandidateEntities extends ImportBase {

  /**
   * Candidate entity records are imported as a multi-value
   * custom field on Contacts
   *
   * The ClassCode is used to reference an exam category by
   * matching to DBPRCode in `pti_Code_Categories`
   */
  protected function import() {
    foreach ($this->getRows("SELECT
        `pti_Candidate_Entity`.`FK_Account_ID`,
        `pti_Candidate_Entity`.`Class_Code`,
        `pti_Candidate_Entity`.`Entity_ID`,
        `pti_Code_Categories`.`Category_Name`
      FROM `pti_Candidate_Entity`
      LEFT JOIN `pti_Code_Categories`
      ON `pti_Candidate_Entity`.`Class_Code` = `pti_Code_Categories`.`DBPRCode`
    ") as $candidateEntity) {

      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $candidateEntity['FK_Account_ID'])
        ->addSelect('id')
        ->execute()->first();

      if (!$contact) {
        \Civi::log()->warning("Imported target contact not found for Candidate Entity with Entity ID {$candidateEntity['Entity_ID']}. Account ID was {$candidateEntity['FK_Account_ID']}");
        continue;
      }

      \Civi\Api4\CustomValue::create('cilb_candidate_entity', FALSE)
        ->addValue('entity_id', $contact['id'])
        ->addValue('Entity_ID_imported_', $candidateEntity['Entity_ID'])
        ->addValue('exam_category:name', $candidateEntity['Category_Name'] ?? NULL)
        ->addValue('class_code', $candidateEntity['Class_Code'] ?? NULL)
        ->execute();
    }
  }
}