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
   * 
   */
  private $files = []; 

  /**
   * Runs the action
   */
  public function _run(Result $result) {
    
    // Get date from param or now()
    $realDate = EU::getTimestampDate($this->dateToSync);
    $this->dateToSync = date('Ymd', $realDate);
    
    $this->files = ['entity' => [], 'scores' => []];

    $destinationDir =  EU::getDestinationDir();
    $result['folder'] = $destinationDir;
    $result['files'] = &$this->files;
    $result['processed'] = ['entity' => [], 'scores' => []];
    
    // Download / Sync CILB entity files and PearsonVUE scores
    $this->downloadCILBEntityFiles();
    $this->downloadPearsonVueFiles();

    // Process Entity files
    if ( count($this->files['entity']) > 0 ) {
      $result['processed']['entity'] = $this->processCILBEntityFiles($destinationDir);
    }

    // Process PearsonVUE files
    if ( count($this->files['scores']) > 0 ) {
      //$result['processed']['scores'] = $this->processPearsonVueFiles($destinationDir);
    }
  

    return $result;
  }

  private function downloadCILBEntityFiles() {
    try {
      $downloadResult = DataSync::syncCILBEntity(FALSE)
        ->setDateToSync($this->dateToSync)
        ->execute();
      $this->files['entity'] = $downloadResult['files']; 
    }
    catch (\CRM_Core_Exception $e) {
      throw new Exception("Error downloading CILB Entity files: " . $e->getMessage());
    }
  }

  private function downloadPearsonVueFiles() {
    try {
      $downloadResult = DataSync::syncPearsonVueScores(FALSE)
        ->setDateToSync($this->dateToSync)
        ->execute();
      $this->files['scores'] = $downloadResult['files']; 
    }
    catch (\CRM_Core_Exception $e) {
      throw new Exception("Error downloadingPearsonVUE files: " . $e->getMessage());
    }
  }

  private function processCILBEntityFiles($directory): array {
    
    $processed = [];
    
    foreach ($this->files['entity'] as $fileName) {
      try {
        $result = civicrm_api3('Job', 'advimportrun', [
          'filename' => $directory . '/' . $fileName,
          'helper' => 'CRM_CILB_Sync_AdvImport_CILBEntityWrapper',
        ]);

        if (!$result) {
          $processed[] = [
            'file'          => $fileName,
            'error'         => 'Error loading file',
          ];
          //throw new Exception("Error loading file: " . $fileName); // doesn't throw error in job, simply returns early
        }

        // Get stats (not resturned by job)
        if ($result['id']) {
          $stats = $this->getImportStats($result['id']);
          $processed[] = [
            'file'          => $fileName,
            'processed_on'  => $stats['end_date'],
            'total'         => $stats['total_count'],
            'processed'     => $stats['success_count'],
            'skipped'       => $stats['warning_count'],
            'errors'        => $stats['error_count'],
          ];
        }
      }
      catch (\CRM_Core_Exception $e) {
        \Civi::log()->debug('Advanced Import failed with message {message}', ['message' => $e->getMessage()]);
        //throw new Exception("Error downloading files: " . $e->getMessage());
        $processed[] = [
          'file'          => $fileName,
          'error'         => 'Error: ' . $e->getMessage(),
        ];
      }
      
    }

    return $processed;
  }

  private function processPearsonVueFiles($directory): array {
    
    $processed = [];
    
    foreach ($this->files['scores'] as $files) {
      foreach ($files as $type => $fileName) {
        try {
          $result = civicrm_api3('Job', 'advimportrun', [
            'filename' => 'test/' . $fileName,
            'helper' => 'CRM_CILB_Sync_AdvImport_CILBEntityWrapper',
          ]);
          \Civi::log()->debug('result -> ' .print_r($result, true) . '');
          $processed[] = $fileName;
        }
        catch (\CRM_Core_Exception $e) {
          \Civi::log()->debug('Advanced Import failed with message {message}', ['message' => $e->getMessage()]);
          throw new Exception("Error downloading files: " . $e->getMessage());
        }
      }
    }

    return $processed;
  }

  private function getImportStats($id): ?array {
    $advimport = \Civi\Api4\Advimport::get(FALSE)
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();

    return $advimport;
  }
  
}
