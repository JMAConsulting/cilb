<?php

namespace Civi\Api4\Action\Job;

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
    
    \Civi::log()->debug("<pre>date --> " . $this->dateToSync . "</pre>");
  }
  
}
