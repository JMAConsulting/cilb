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
class ImportPlumbingRegistrations extends ImportRegistrationsBase {

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

    //$this->buildEventMap();
    $this->importParts();
  }

  protected function findOrCreateExam($examId) {
    foreach ($this->getRows("
      SELECT
        PK_Exam_Event_ID,
        Threshold,
        Actual_Exam_Date,
        Scheduled_Exam_Date
      FROM
        pti_Exam_Events
      WHERE
        `PK_Exam_Event_ID` = {$examId}
    ") as $exam) {
      $event = \Civi\Api4\Event::save(FALSE)
        ->addRecord([
          'title' => "Plumbing - " . date('Y-m-d', strtotime($exam['Actual_Exam_Date'])),
          'max_participants' => $exam['Threshold'],
          'start_date' => $exam['Scheduled_Exam_Date'],
          'is_online_registration' => TRUE,
          'registration_start_date' => date('Y-m-d', strtotime($exam['Actual_Exam_Date'])),
          'event_type_id:name' => 'Plumbing',
          'is_online_registration' => TRUE,
          'Exam_Details.Exam_ID' => $exam['PK_Exam_Event_ID'],
          'Exam_Details.Exam_Part' => 'TK',
          'is_active' => TRUE,
          'Exam_Details.Exam_Format' => 'paper',
        ])
        ->setMatch(['Exam_Details.Exam_ID'])
        ->execute()->first();

      // Add the price field, since this is a TK Plumbing Exam.
      \Civi\Api4\PriceSetEntity::save(FALSE)
        ->addRecord([
            'entity_table' => 'civicrm_event',
            'entity_id' => $event['id'],
            'price_set_id.name' => 'Seat_Fee_80_DPBR'
        ])
        ->setMatch(['entity_table', 'entity_id'])
        ->execute();
      return $event['id'];
    }
  }

  protected function importParts() {
    foreach ($this->getRows("
        SELECT
        PK_Exam_Registration_ID,
        PK_Exam_Registration_Part_ID,
        FK_Account_ID,
        er.FK_Category_ID,
        er.FK_Exam_Event_ID,
        Candidate_Number,
        Category_Name,
        Transaction_Date,
        erp.FK_Exam_Part_ID,
        COALESCE(cep.Exam_Part_Name_Abbr, eeep.Exam_Part_Name_Abbr) as Exam_Part_Name_Abbr,
        Pass,
        Fee_Amount,
        Payment_Method,
        Seat_Fee_Amount,
        Registration_Status,
        Check_Number,
        Score,
        ee.Actual_Exam_Date as Actual_Exam_Date
        FROM pti_Exam_Registrations er

        JOIN pti_Code_Categories cc
        ON FK_Category_ID = PK_Category_ID

        JOIN pti_Exam_Registration_Parts erp
        ON erp.FK_Exam_Registration_ID = er.PK_Exam_Registration_ID

        JOIN pti_Exam_Events ee
        ON ee.PK_Exam_Event_ID = er.FK_Exam_Event_ID

        LEFT OUTER JOIN pti_Code_Exam_Parts cep
        ON erp.FK_Exam_Part_ID = cep.PK_Exam_Part_ID

        LEFT OUTER JOIN pti_Exam_Event_Exam_Parts eeep
        ON eeep.PK_Exam_Event_Exam_Part_ID = erp.FK_Exam_Part_ID

        WHERE er.Transaction_Date > '{$this->cutOffDate}'
        AND YEAR(er.Transaction_Date) = '{$this->transactionYear}'
        AND er.Registration_Status IN ('Registration Complete', 'Registration Paid')
        AND er.FK_Category_ID = 406;
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

    //$event = $this->eventMap[$registration['Category_Name']][$registration['Exam_Part_Name_Abbr']] ?? NULL;
    // Find existing or create new plumbing event.
    $event = $this->findOrCreateExam($registration['FK_Exam_Event_ID']);

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

    if (empty($registration['Actual_Exam_Date'])) {
      $status = 'Registered';
    }

    $participantId = \Civi\Api4\Participant::create(FALSE)
      ->addValue('event_id', $event)
      ->addValue('contact_id', $contactId)
      ->addValue('register_date', $registration['Transaction_Date'])
      ->addValue('Candidate_Result.Candidate_Score', $registration['Score'])
      ->addValue('status_id:name', $status)
      ->addValue('source', $registration['PK_Exam_Registration_Part_ID'])
      ->addValue('Candidate_Result.Candidate_Number', $registration['Candidate_Number'])
      ->addValue('Candidate_Result.Date_Exam_Taken', $registration['Actual_Exam_Date'])
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
      $registration['Seat_Fee_Amount'],
      "Account ID ({$registration['FK_Account_ID']} - Registration Part ID ({$registration['PK_Exam_Registration_Part_ID']})"
    );
  }
}
