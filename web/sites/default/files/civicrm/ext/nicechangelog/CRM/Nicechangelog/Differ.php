<?php

class CRM_Nicechangelog_Differ extends CRM_Logging_ReportDetail {

  public function __construct() {
    $this->storeDB();
  }

  /**
   * Return the formatted, one-line-per-field changes for a connection.
   *
   * @param string $connId
   * @param string $logDate
   * @param int $contactId
   * @param array $tables
   *   Un-prefixed entity tables to inspect (e.g. ['civicrm_contact']).
   *
   * @return array
   */
  public function getDiffRows(string $connId, ?string $logDate, int $contactId, array $tables): array {
    if (empty($connId) || empty($tables)) {
      return [];
    }
    $this->log_conn_id = $connId;
    $this->log_date = $logDate;
    $this->cid = $contactId;
    $this->raw = FALSE;
    $this->interval = '10 SECOND';
    $this->differ = new CRM_Logging_Differ($connId, $logDate, $this->interval);

    $this->diffs = [];
    foreach ($tables as $table) {
      try {
        $this->diffs = array_merge($this->diffs, $this->differ->diffsInTable($table, $contactId));
      }
      catch (Exception $e) {
      }
    }

    if (empty($this->diffs)) {
      return [];
    }

    $flat = [];
    foreach ($this->convertDiffsToRows() as $row) {
      if (is_array($row['field'])) {
        foreach ($row['field'] as $i => $field) {
          $flat[] = [
            'field' => (string) $field,
            'from' => (string) ($row['from'][$i] ?? ''),
            'to' => (string) ($row['to'][$i] ?? ''),
          ];
        }
      }
      else {
        $flat[] = [
          'field' => (string) $row['field'],
          'from' => (string) ($row['from'] ?? ''),
          'to' => (string) ($row['to'] ?? ''),
        ];
      }
    }
    return $flat;
  }

}
