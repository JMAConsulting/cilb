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
abstract class ImportBase extends \Civi\Api4\Generic\AbstractAction {

  /**
   * @var string
   *
   * DSN for the source database
   */
  protected string $sourceDsn = '';

  /**
   * @var string
   *
   * cut off date for imports
   * in mysql string format
   */
  protected string $cutOffDate;

  /**
   * @var int
   *
   * max records to select from each table
   * (rough way to do a partial test import)
   */
  protected ?int $recordLimit = NULL;

  /**
   * @var DB
   *
   * DB connection object for the source database
   */
  private $conn;

  public function _run(\Civi\Api4\Generic\Result $result) {

    $this->sourceDsn = $this->sourceDsn ?: getenv('CILB_IMPORT_DSN');

    $this->conn = \DB::connect($this->sourceDsn);

    $this->import();
  }

  protected function getRows(string $query) {
    // add limit clause if set
    $query .= $this->recordLimit ? " LIMIT {$this->recordLimit}" : "";
    
    $results = $this->conn->query($query);
    while ($row = $results->fetchRow(DB_FETCHMODE_ASSOC)) {
      yield $row;
    }
  }

  protected function info(string $msg) {
    echo "$msg\n\n";
    \Civi::log()->debug($msg);
  }

  protected function warning(string $msg) {
    echo "[WARNING] $msg\n\n";
    \Civi::log()->warning($msg);
  }

  protected function updateExamLocation($examID, $eventID) {
    // Check to see if we have location info for this exam.
    $locBlock = \Civi\Api4\Event::get(FALSE)
      ->addSelect('loc_block_id')
      ->addWhere('id', '=', $eventID)
      ->execute()->first();
    if (!empty($locBlock['loc_block_id'])) {
      // We already have a location block - nothing to do.
      return;
    }
    foreach ($this->getRows("
      SELECT
        Address1,
        Address2,
        City,
        Zip
        FROM pti_Exam_Events
        JOIN pti_Exam_Sites
        ON pti_Exam_Events.`FK_Exam_Site_ID` = pti_Exam_Sites.`PK_Exam_Site_ID`
        JOIN pti_Exam_Areas
        ON pti_Exam_Sites.`FK_Exam_Area_ID` = pti_Exam_Areas.`PK_Exam_Area_ID`
        WHERE PK_Exam_Event_ID = {$examID}
      ") as $eventLocation) {
      $addressParams = [
        'location_type_id' => 1,
        'street_address' => $eventLocation['Address1'],
        'supplemental_address_1' => $eventLocation['Address2'],
        'city' => $eventLocation['City'],
        'state_province_id:name' => 'Florida',
        'postal_code' => $eventLocation['Zip'],
        'country_id:name' => 'United States',
      ];
      if (!empty($eventLocation['Address1'])) {
		    $address = civicrm_api4('Address', 'create', [
			    'values' => $addressParams,
			    'checkPermissions' => FALSE
		    ])->first()['id'];
        $locBlockId = \Civi\Api4\LocBlock::create(FALSE)
          ->addValue('address_id', $address)
          ->execute()->first()['id'];
        \Civi\Api4\Event::update(FALSE)
          ->addValue('loc_block_id', $locBlockId)
          ->addValue('Exam_Details.Exam_ID', $examID)
          ->addWhere('id', '=', $eventID)
	        ->execute();
      }
    }
  }

  abstract protected function import();

}
