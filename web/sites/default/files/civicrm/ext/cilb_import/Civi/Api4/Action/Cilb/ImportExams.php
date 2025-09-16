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
          `pti_Code_Categories`
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

    $options = \Civi\Api4\OptionValue::get(FALSE)->addWhere('option_group_id:name', '=', 'event_type')->execute();
    $bf_event_type_id = $bf_ps_event_type_id = 0;
    foreach ($options as $option) {
      if ($option['name'] == 'Business and Finance') {
        $bf_event_type_id = $option['value'];
      }
      else if ($option['name'] == 'Pool & Spa Servicing Business and Finance') {
        $bf_ps_event_type_id = $option['value'];
      }
    }

    // TO CHECK: pti_category_exam_parts or pti_Code_Exam_Parts
    // spec says pti_category_exam_parts;
    // but pti_Code_Exam_Parts contains all the data from pti_category_exam_parts
    // as well as the Business and Finance exams, which match the Google Sheet
    // of expected parts for each Exam Category
    foreach ($this->getRows("
    SELECT
        `part`.`PK_Exam_Part_ID`, `part`.`FK_Category_ID`, `part`.`Exam_Part_Name`, `part`.`Exam_Part_Name_Abbr`, `part`.`Exam_Part_Sequence`,

        `category`.`Category_Name`, `category`.`Begin_Date`, `category`.`CategoryID`,

        `dbpr_info`.`Exam_Series_Code`, `dbpr_info`.`Number_Exam_Questions`

    FROM
        `pti_Code_Exam_Parts` as `part`
    JOIN
        `pti_Code_Categories` as `category`
    ON
        `part`.`FK_Category_ID` = `category`.`PK_Category_ID`
    LEFT JOIN
        `pti_Category_Exam_Parts_DBPR_Xref` as `dbpr_info`
    ON
        `part`.`PK_Exam_Part_ID` = `dbpr_info`.`PK_Exam_Part_ID`
      ") as $sourceEvent) {

      $eventValues = $this->mapEventFields($sourceEvent);

      if ($eventValues['Exam_Details.Exam_Part'] === 'BF') {
        $correct_event_type_id = $bf_event_type_id;
        if ($eventValues['event_type_id:name'] == 'Pool/Spa Servicing') {
          $correct_event_type_id = $bf_ps_event_type_id;
        }
        $eventCheck = \Civi\Api4\Event::get(FALSE)
          ->addSelect(...array_keys($eventValues))
          ->addWhere('event_type_id', '=', $correct_event_type_id)
          ->addWhere('Exam_Details.Exam_Part', '=', $eventValues['Exam_Details.Exam_Part'])
          ->addWhere('is_active', '=', TRUE);
        if ($correct_event_type_id == $bf_ps_event_type_id) {
          $eventCheck->addWhere('Exam_Details.Exam_Category_this_exam_applies_to:name', 'CONTAINS', $eventValues['event_type_id:name']);
          $eventValues['Exam_Details.Exam_Category_this_exam_applies_to:name'] = [$eventValues['event_type_id:name']];
        }
        $match = $eventCheck->execute()->first();
        $eventValues['event_type_id'] = $correct_event_type_id;
        unset($eventValues['event_type_id:name']);
      }
      else {
        $match = \Civi\Api4\Event::get(FALSE)
          ->addSelect(...array_keys($eventValues))
          ->addWhere('event_type_id:name', '=', $eventValues['event_type_id:name'])
          ->addWhere('Exam_Details.Exam_Part', '=', $eventValues['Exam_Details.Exam_Part'])
          ->addWhere('is_active', '=', TRUE)
          ->execute()
          ->first();
      }

      if ($match) {
        $this->info("Found existing event for {$eventValues['title']}");
        foreach ($eventValues as $key => $value) {
          $existingValue = $match[$key];
          if ($existingValue !== $value) {
            if ($existingValue || $value) {
              $this->warning("Imperfect match on {$key} - discarding import value {$value}, leaving existing {$existingValue}");
            }
          }
        }
        continue;
      }

      // no match, create new event
      $this->info("Importing event {$eventValues['title']}");
      \Civi\Api4\Event::create(FALSE)
        ->setValues($eventValues)
        ->execute();

    }
  }

  protected function mapEventFields(array $sourceEvent): array {
    return [
        'event_type_id:name' => $sourceEvent['Category_Name'],
        'start_date' => $sourceEvent['Begin_Date'],
        'title' => $sourceEvent['Category_Name'] . ' - ' . $sourceEvent['Exam_Part_Name'],
        'is_online_registration' => TRUE,
        'Exam_Details.imported_id' => $sourceEvent['PK_Exam_Part_ID'],
        'Exam_Details.Exam_Series_Code' => $sourceEvent['Exam_Series_Code'] ?? NULL,
        'Exam_Details.Exam_Question_Count' => $sourceEvent['Number_Exam_Questions'] ?? NULL,
          // option values for exam parts created as managed record
          // @see managed/OptionGroup_EventPart.mgd.php
        'Exam_Details.Exam_Part' => $sourceEvent['Exam_Part_Name_Abbr'],
        'Exam_Details.Exam_Part_Sequence' => $sourceEvent['Exam_Part_Sequence'],
      ];
  }
}
