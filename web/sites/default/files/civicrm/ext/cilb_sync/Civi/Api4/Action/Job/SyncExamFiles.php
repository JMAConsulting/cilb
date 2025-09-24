<?php

namespace Civi\Api4\Action\Job;

use CRM_CILB_Sync_Utils as EU;

use \Civi\Api4\Generic\Result;
use \Civi\Api4\DataSync;
use Exception;

/**
 * Automatically download CILB Exam files and trigger an import
 */

class SyncExamFiles extends \Civi\Api4\Generic\AbstractAction {
  /**
   * ...
   *
   * @var string
   */
  protected $dateToSync;

  /**
   * @var null
   */
  protected $language = NULL;
  
  /**
   * @var null
   */
  protected $chain = [];

  /**
   * Runs the action
   */
  public function _run(Result $result) {
    
    // Get date from param or now()
    $realDate = EU::getTimestampDate($this->dateToSync);
    $this->dateToSync = date('Ymd', $realDate);


    \Civi::log()->debug("<pre>date --> " . $this->dateToSync . "</pre>");
    
    
    // Download / Sync CILB entity files and PearsonVUE scores
    try {
      $result = DataSync::syncCILBEntities(FALSE)
        ->setDateToSync($this->dateToSync)
        ->execute();

      //DataSync::syncCILBEntities(FALSE)->setDateToSync($this->dateToSync);
      //DataSync::syncPearsonVueScores(FALSE)->setDateToSync($this->dateToSync);
    }
    catch (\CRM_Core_Exception $e) {
      throw new Exception("Error downloading files: " . $e->getMessage());
    }
  }
  
}
