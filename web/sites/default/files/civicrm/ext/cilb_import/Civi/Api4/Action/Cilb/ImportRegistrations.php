<?php

namespace Civi\Api4\Action\Cilb;

/**
 * run with cv api4 on the command line
 *
 * e.g.
 * cv api4 Cilb.importRegistrations sourceDsn=[] \
 *  cutOffDate=2019-09-01 \
 *  transactionYear=2020
 */
class ImportRegistrations extends ImportRegistrationsBase {

  /**
   * @var string
   * @required
   *
   * 4 digit year to enable importing in segments
   */
  protected string $transactionYear;

  protected array $eventMap = [];

  protected function import() {
    $this->info("Importing registrations for {$this->transactionYear}...");

    $this->buildEventMap();
    $this->importParts();
  }

  protected function importParts() {
    foreach ($this->getRows("
        SELECT
          PK_Exam_Registration_ID,
          PK_Exam_Registration_Part_ID,
          FK_Account_ID,
          pti_Exam_Registrations.FK_Category_ID,
          Category_Name,
          Transaction_Date,
          Exam_Part_Name_Abbr,
          Candidate_Number,
          CBT_Exam_Date,
          FK_Exam_Event_ID,
          Pass,
          Fee_Amount,
          Payment_Method,
          Seat_Fee_Amount,
          Registration_Status,
          Check_Number,
          Score
        FROM pti_Exam_Registrations

        JOIN pti_Code_Categories
        ON `FK_Category_ID` = `PK_Category_ID`

        JOIN pti_Exam_Registration_Parts
        ON pti_Exam_Registration_Parts.`FK_Exam_Registration_ID` = pti_Exam_Registrations.`PK_Exam_Registration_ID`

        JOIN pti_Code_Exam_Parts
        ON pti_Exam_Registration_Parts.`FK_Exam_Part_ID` = pti_Code_Exam_Parts.`PK_Exam_Part_ID`

        WHERE
        Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(Transaction_Date) = '{$this->transactionYear}'
        AND Exam_Part_Name_Abbr != 'BF'
        AND Registration_Status IN ('Registration Complete', 'Registration Paid')
    ") as $registration) {
      try {
        $this->importRegistrationRow($registration);
      }
      catch (\Exception $e) {
        $this->warning($e->getMessage() . " when importing " . \json_encode($registration, \JSON_PRETTY_PRINT));
      }
    }
  }

  protected function importRegistrationRow($registration) {
    $contactId = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('external_identifier', '=', $registration['FK_Account_ID'])
      ->execute()->first()['id'] ?? NULL;

    if (!$contactId) {
      return;
      //throw new \Exception('No contact id found for Account ID: ' . $registration['FK_Account_ID']);
    }

    $event = $this->eventMap[$registration['Category_Name']][$registration['Exam_Part_Name_Abbr']] ?? NULL;

    if (!$event) {
      return;
      //throw new \Exception("No event found for registration ID {$registration['PK_Exam_Registration_ID']}");
    }

    /**
     * Note source data has 0, 1, and NULL
     */
    $status = match ($registration['Pass'] ?? NULL) {
      '1' => 'Pass',
      '0' => 'Fail',
      default => 'Registered',
    };

    if (empty($registration['CBT_Exam_Date'])) {
      $status = 'Registered';
    }

    $participantId = \Civi\Api4\Participant::create(FALSE)
      ->addValue('event_id', $event)
      ->addValue('contact_id', $contactId)
      ->addValue('register_date', $registration['Transaction_Date'])
      ->addValue('Candidate_Result.Candidate_Score', $registration['Score'])
      ->addValue('status_id:name', $status)
      ->addValue('Candidate_Result.Date_Exam_Taken', $registration['CBT_Exam_Date'])
      ->addValue('source', $registration['PK_Exam_Registration_Part_ID'])
      ->addValue('Candidate_Result.Candidate_Number', $registration['Candidate_Number'])
      ->addValue('Candidate_Result.Registration_Expiry_Date', date('Y-m-d H:i:s', strtotime($registration['Transaction_Date'] . ' +5 years')))
      ->execute()->first()['id'];

    // Update the exam location as well.
    if (!empty($registration['FK_Exam_Event_ID'])) {
      $this->updateExamLocation($registration['FK_Exam_Event_ID'], $event);
    }

    $this->recordPayments(
      $registration,
      $contactId,
      $participantId,
      $event,
      NULL, // all seat fees are external
      "Account ID ({$registration['FK_Account_ID']} - Registration Part ID ({$registration['PK_Exam_Registration_Part_ID']})"
    );

  }
}
