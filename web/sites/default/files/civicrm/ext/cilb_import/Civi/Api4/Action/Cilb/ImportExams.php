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
class ImportExams extends ImportBase {

  protected function import() {
    $this->importEventTypes();
    $this->importEvents();
  }

  public function importEventTypes() {

    foreach ($this->getRows("
      SELECT
          `PK_Category_ID`, `Category_Name`, `Specialty_ID`, `Begin_Date`,
          `DBPRCode`, `CategoryID`, `CILB_Class`, `GusCode`, `GusDBPRCode`, `Category_Name_Spanish`
      FROM
          `pti_code_categories`
      ") as $eventCategory) {

      \Civi\Api4\OptionValue::save(FALSE)
        ->addRecord([
          'option_group_id.name' => 'event_type',
          'label' => $eventCategory['Category_Name'],
          'name' => $eventCategory['Category_Name'],
          'Exam_Type_Details.imported_id' => $eventCategory['PK_Category_ID'],
          'Exam_Type_Details.Speciality_ID' => $eventCategory['Specialty_ID'],
          'Exam_Type_Details.DBPR_Code' => $eventCategory['DBPRCode'],
          'Exam_Type_Details.CILB_Class' => $eventCategory['CILB_Class'],
          'Exam_Type_Details.Gus_Code' => $eventCategory['GusCode'],
          'Exam_Type_Details.Gus_DBPR_Code' => $eventCategory['GusDBPRCode'],
          'Exam_Type_Details.Category_Name_Spanish' => $eventCategory['Category_Name_Spanish'],
        ])
        ->setMatch(['option_group_id', 'name'])
        ->execute();
    }

  }

  public function importEvents() {

    // TO CHECK: pti_category_exam_parts or pti_code_exam_parts
    // spec says pti_category_exam_parts;
    // but pti_code_exam_parts contains all the data from pti_category_exam_parts
    // as well as the Business and Finance exams, which match the Google Sheet
    // of expected parts for each Exam Category
    foreach ($this->getRows("
    SELECT
        `part`.`PK_Exam_Part_ID`, `part`.`FK_Category_ID`, `part`.`Exam_Part_Name`, `part`.`Exam_Part_Name_Abbr`, `part`.`Exam_Part_Sequence`,

        `category`.`Category_Name`, `category`.`Begin_Date`, `category`.`CategoryID`,

        `dbpr_info`.`Exam_Series_Code`, `dbpr_info`.`Number_Exam_Questions`

    FROM
        `pti_code_exam_parts` as `part`
    JOIN
        `pti_code_categories` as `category`
    ON
        `part`.`FK_Category_ID` = `category`.`PK_Category_ID`
    LEFT JOIN
        `pti_category_exam_parts_dbpr_xref` as `dbpr_info`
    ON
        `part`.`PK_Exam_Part_ID` = `dbpr_info`.`PK_Exam_Part_ID`
      ") as $event) {

      \Civi\Api4\Event::create(FALSE)
        ->addValue('event_type_id:name', $event['Category_Name'])
        ->addValue('start_date', $event['Begin_Date'])
        ->addValue('title', $event['Category_Name'] . ' - ' . $event['Exam_Part_Name'])
        ->addValue('is_online_registration', TRUE)
        ->addValue('Exam_Details.imported_id', $event['PK_Exam_Part_ID'])
        ->addValue('Exam_Details.Exam_Series_Code', $event['Exam_Series_Code'] ?? NULL)
        ->addValue('Exam_Details.Exam_Question_Count', $event['Number_Exam_Questions'] ?? NULL)
          // option values for exam parts created as managed record
          // @see managed/OptionGroup_EventPart.mgd.php
        ->addValue('Exam_Details.Exam_Part', $event['Exam_Part_Name_Abbr'])
        ->addValue('Exam_Details.Exam_Part_Sequence', $event['Exam_Part_Sequence'])
        ->execute();
    }
  }
}