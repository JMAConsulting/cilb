<?php

class CRM_Nicechangelog_Report_Summary extends CRM_Report_Form_Contact_LoggingSummary {

  /**
   *
   * @param int $contactId
   * @param array $dateParams
   *
   * @return array
   */
  public function fetchRawRows(int $contactId, array $dateParams = []): array {
    $params = [
      'report_id' => 'logging/contact/summary',
      'altered_contact_id_op' => 'eq',
      'altered_contact_id_value' => $contactId,
      'log_type_op' => 'in',
      'fields' => [
        'log_action' => '1',
        'log_type' => '1',
        'log_date' => '1',
        'altered_contact' => '1',
        'display_name' => '1',
      ],
    ] + $dateParams;

    $this->setParams($params);
    $this->noController = TRUE;
    $this->preProcess();
    $this->setDefaultValues(FALSE);
    $this->setParams(array_merge($this->getDefaultValues(), $params));
    $this->setLimitValue(0);
    $this->setAddPaging(FALSE);
    $this->setOffsetValue(0);
    $this->beginPostProcessCommon();
    $sql = (string) $this->buildQuery();
    $rows = [];
    $this->buildRows($sql, $rows);
    return $rows;
  }

  /**
   *
   * @param string|null $logTable
   *
   * @return string|null
   */
  public function getLogTypeRaw(?string $logTable): ?string {
    return $this->_logTables[$logTable]['log_type'] ?? NULL;
  }

  /**
   *
   * @return array
   */
  public function getTablesByType(): array {
    $map = [];
    foreach ($this->_logTables as $logTable => $detail) {
      $type = $this->getLogType($logTable);
      $source = $detail['table_name'] ?? $logTable;
      $real = preg_replace('/^log_/', '', $source);
      $map[$type][$real] = $real;
    }
    return $map;
  }

}
