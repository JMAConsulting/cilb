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

  abstract protected function import();

}
