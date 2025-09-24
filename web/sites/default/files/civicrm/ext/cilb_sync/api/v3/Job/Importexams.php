<?php

/**
 * Import Exam Scores from Pearson View
 */
function civicrm_api3_job_importexams($params) {
  try {
    // Download / Sync today's exam dates.
    \Civi\Api4\DataSync::syncPearsonVueEntity(FALSE)->setDateToSync(date('Y-m-d'));
    \Civi\Api4\DataSync::syncPearsonVueScores(FALSE)->setDateToSync(date('Y-m-d'));
  }
  catch (\CRM_Core_Exception $e) {
    return civicrm_api3_create_error($e->getMessage());
  }
  $config = CRM_Core_Config::singleton();
  $dstdir = $config->customFileUploadDir . '/advimport/test';
  $processedDir = $config->customFileUploadDir . '/advimport-processed/';
  // Try creating the processed directory to move our files into after processing.
  CRM_Utils_File::createDir($processedDir);
  $files_processed = ['entity' => 0, 'exam' => 0];
  $entityImportFiles = CRM_Utils_File::findFiles($dstdir, '*.csv');
  foreach ($entityImportFiles as $entityImportFile) {
    try {
      civicrm_api3('Job', 'advimportrun', [
        'filename' => $entityImportFile,
        'helper' => 'CRM_CILB_Sync_AdvImport_PearsonViewEntity',
      ]);
    }
    catch (\CRM_Core_Exception $e) {
      \Civi::log()->debug('Advanced Import failed with message {message}', ['message' => $e->getMessage()]);
      return civicrm_api3_create_error($e->getMessage());
    }
    copy($entityImportFile, $processedDir);
    if (!unlink($entityImportFile)) {
      Civi::log()->debug('Cannot remove import file {file}', ['file' => $entityImportFile]);
    }
    $files_processed['entity']++;
  }
  $importFiles = CRM_Utils_File::findFiles($dstdir, '*.dat');
  foreach ($importFiles as $importFile) {
    try {
      civicrm_api3('Job', 'advimportrun', [
        'filename' => $importFile,
        'helper' => 'CRM_CILB_Sync_AdvImport_PearsonVueWrapper',
      ]);
    }
    catch (\CRM_Core_Exception $e) {
      Civi::log()->debug('Advanced Import failed with message {message}', ['message' => $e->getMessage()]);
      return civicrm_api3_create_error($e->getMessage());
    }
    copy($importFile, $processedDir);
    if (!unlink($importFile)) {
      Civi::log()->debug('Cannot remove import file {file}', ['file' => $importFile]);
    }
    $files_processed['exam']++;
  }
  return civicrm_api3_create_success($files_processed, $params);

}