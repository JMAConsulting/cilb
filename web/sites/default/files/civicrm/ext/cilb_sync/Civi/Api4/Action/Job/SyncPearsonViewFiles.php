<?php

namespace Civi\Api4\Action\Job;

use CRM_CILB_Sync_Utils as EU;

use \Civi\Api4\Generic\Result;
use \Civi\Api4\DataSync;
use Exception;

/**
 * Automatically download CILB Exam files and trigger an import
 */

class SyncPearsonViewFiles extends \Civi\Api4\Generic\AbstractAction {
  /**
   * ...
   *
   * @var string
   */
  protected $dateToSync;

  /**
   * @var bool
   */
  protected $runInNonProductionEnvironment = TRUE;

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
    try {
      $this->downloadPearsonVueFiles();

    }
    catch (\Exception $e) {
      $result['errors'] = ['scores' => $e->getMessage()];
    }

    // Process PearsonVUE files
    if ( count($this->files['scores']) > 0 ) {
      $result['processed']['scores'] = $this->processPearsonVueFiles($destinationDir);
    }

    return $result;
  }

  /**
   * Download PearsonVUE score files for select data
   */
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

  /**
   * Import scores from downloaded PearsonVUE files
   */
  private function processPearsonVueFiles($directory): array {
    $processed = [];
    foreach ($this->files['scores'] as $files) {
      if (!is_array($files)) {
        continue;
      }
      foreach ($files as $type => $fileName) {
        $processed[] = $this->processImportFile($fileName, $directory, 'PearsonVueWrapper');
      }
    }
    return $processed;
  }

  /**
   * Process import for select file
   * using Job.advimportrun
   */
  private function processImportFile($fileName, $directory, $helperClass): array {
    try {

      // check if exists first
      $importID = $this->getPreviousImportID($fileName);

      // Already exists. Do we re-try?
      if (!empty($importID)) {
        $returnArr = [
          'file'          => $fileName,
          'error'         => 'Skipped. Already processed.',
        ];
        return $returnArr;
      }

      $result = civicrm_api3('Job', 'advimportrun', [
        'filename' => $directory . '/' . $fileName,
        'helper' => 'CRM_CILB_Sync_AdvImport_' . $helperClass,
      ]);

      if (!$result) {
        $returnArr = [
          'file'          => $fileName,
          'error'         => 'Error loading file',
        ];
        //throw new Exception("Error loading file: " . $fileName); // doesn't throw error in job, simply returns early
        return $returnArr;
      }

      // Get stats (not returned by job)
      if ($result['id']) {
        $stats = $this->getImportStats($result['id']);
        $returnArr = [
          'file'          => $fileName,
          'processed_on'  => $stats['end_date'],
          'total'         => $stats['total_count'],
          'processed'     => $stats['success_count'],
          'skipped'       => $stats['warning_count'],
          'errors'        => $stats['error_count'],
        ];

        // Filename not saved by job
        if (empty($stats['filename'])) {
          $results = \Civi\Api4\Advimport::update(FALSE)
            ->addValue('filename', $fileName)
            ->addWhere('id', '=', $result['id'])
            ->execute();
        }
      }
    }
    catch (\CRM_Core_Exception $e) {
      \Civi::log()->debug('Advanced Import failed with message {message}', ['message' => $e->getMessage()]);
      //throw new Exception("Error downloading files: " . $e->getMessage());
      $returnArr = [
        'file'          => $fileName,
        'error'         => 'Error: ' . $e->getMessage(),
      ];
    }

    return $returnArr;
  }

  /**
   * Get import results
   */
  private function getImportStats($id): ?array {
    $advimport = \Civi\Api4\Advimport::get(FALSE)
      ->addWhere('id', '=', $id)
      ->execute()
      ->first();

    return $advimport;
  }

  /**
   * Get the import ID if file already prcoessed before
   */
  private function getPreviousImportID($filename): ?int {
    $advimport = \Civi\Api4\Advimport::get(FALSE)
      ->addWhere('filename', '=', $filename)
      ->addWhere('end_date', 'IS NOT NULL')
      ->addOrderBy('end_date', 'DESC') // get latest, in case we have multiple
      ->execute()
      ->first();

    return $advimport['id'] ?? NULL;
  }

}
