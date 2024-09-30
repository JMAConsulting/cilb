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
class ImportRegistrations extends ImportBase {

  protected function import() {
    foreach ($this->getRows("
        SELECT FK_Account_ID, FK_Category_ID, Confirm_BF_Exam, Category_Name
        FROM pti_exam_registrations
        JOIN pti_code_categories
        ON `FK_Category_ID` = `PK_Category_ID`
        WHERE Transaction_Date > '{$this->cutOffDate}'
    ") as $registration) {

      $contactId = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
        ->execute()->first()['id'] ?? NULL;

      if (!$contactId) {
        \Civi::log()->warning('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
        continue;
      }

      $categoryEvents = \Civi\Api4\Event::get(FALSE)
        ->addWhere('event_type_id:name', '=', $registration['Category_Name']);

      if (!$registration['Confirm_BF_Exam']) {
        $categoryEvents->addWhere('Exam_Details.Exam_Part', '!=', 'BF');
      }

      $categoryEvents = $categoryEvents->execute();

      foreach ($categoryEvents as $event) {
        \Civi\Api4\Participant::create(FALSE)
          ->addValue('event_id', $event['id'])
          ->addValue('contact_id', $contactId)
          ->execute();
      }
    }
  }
}