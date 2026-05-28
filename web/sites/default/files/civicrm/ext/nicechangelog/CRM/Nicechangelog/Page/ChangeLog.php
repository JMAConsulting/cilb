<?php

use CRM_Nicechangelog_ExtensionUtil as E;

class CRM_Nicechangelog_Page_ChangeLog extends CRM_Core_Page {


  const SUMMARY_FIELD_LIMIT = 4;

  public function run() {
    $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $this, TRUE);

    if (!CRM_Contact_BAO_Contact_Permission::allow($contactId, CRM_Core_Permission::VIEW)) {
      CRM_Core_Error::statusBounce(E::ts('You do not have permission to view this contact.'));
    }

    $range = CRM_Utils_Request::retrieve('ncl_range', 'String', $this) ?: 'this_month';
    $from = CRM_Utils_Request::retrieve('ncl_from', 'String', $this);
    $to = CRM_Utils_Request::retrieve('ncl_to', 'String', $this);
    $dateRange = $this->resolveDateRange($range, $from, $to);

    $this->assign('contactId', $contactId);
    $this->assign('displayName', CRM_Contact_BAO_Contact::displayName($contactId));
    $this->assign('datePresets', self::datePresets());
    $this->assign('selectedRange', $dateRange['range']);
    $this->assign('customFrom', $dateRange['from']);
    $this->assign('customTo', $dateRange['to']);

    [$rows, $actionOptions, $componentOptions] = $this->buildChangeRows($contactId, $dateRange['reportParams']);

    $this->assign('rows', $rows);
    $this->assign('actionOptions', $actionOptions);
    $this->assign('componentOptions', $componentOptions);
    $this->assign('rowCount', count($rows));

    CRM_Core_Resources::singleton()
      ->addStyleFile(E::LONG_NAME, 'css/nicechangelog.css')
      ->addScriptFile(E::LONG_NAME, 'js/nicechangelog.js');

    return parent::run();
  }

  /**
   *
   * @return array
   */
  public static function datePresets(): array {
    return [
      'this_month' => E::ts('This month'),
      'last_month' => E::ts('Last month'),
      'last_7' => E::ts('Last 7 days'),
      'last_30' => E::ts('Last 30 days'),
      'this_year' => E::ts('This year'),
      'all' => E::ts('All time'),
      'custom' => E::ts('Custom range'),
    ];
  }

  /**
   *
   * @param string $range
   * @param string|null $from
   * @param string|null $to
   *
   * @return array
   */
  protected function resolveDateRange(string $range, ?string $from, ?string $to): array {
    if (!array_key_exists($range, self::datePresets())) {
      $range = 'this_month';
    }

    $fromDate = $toDate = NULL;
    switch ($range) {
      case 'all':
        break;

      case 'custom':
        $fromDate = $from ? date('Y-m-d 00:00:00', strtotime($from)) : NULL;
        $toDate = $to ? date('Y-m-d 23:59:59', strtotime($to)) : NULL;
        break;

      case 'last_month':
        $fromDate = date('Y-m-01 00:00:00', strtotime('first day of last month'));
        $toDate = date('Y-m-t 23:59:59', strtotime('last day of last month'));
        break;

      case 'last_7':
        $fromDate = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $toDate = date('Y-m-d 23:59:59');
        break;

      case 'last_30':
        $fromDate = date('Y-m-d 00:00:00', strtotime('-29 days'));
        $toDate = date('Y-m-d 23:59:59');
        break;

      case 'this_year':
        $fromDate = date('Y-01-01 00:00:00');
        $toDate = date('Y-12-31 23:59:59');
        break;

      case 'this_month':
      default:
        $fromDate = date('Y-m-01 00:00:00');
        $toDate = date('Y-m-t 23:59:59');
        break;
    }

    $reportParams = [];
    if ($fromDate) {
      $reportParams['log_date_from'] = $fromDate;
    }
    if ($toDate) {
      $reportParams['log_date_to'] = $toDate;
    }

    return ['range' => $range, 'from' => $from, 'to' => $to, 'reportParams' => $reportParams];
  }

  /**
   *
   * @param int $contactId
   * @param array $dateParams
   *
   * @return array
   */
  protected function buildChangeRows(int $contactId, array $dateParams = []): array {
    $report = new CRM_Nicechangelog_Report_Summary();
    $rawRows = $report->fetchRawRows($contactId, $dateParams);
    $tablesByType = $report->getTablesByType();

    $differ = new CRM_Nicechangelog_Differ();

    $deduped = [];
    foreach ($rawRows as $raw) {
      $connId = $raw['log_civicrm_entity_log_conn_id'] ?? NULL;
      $logDate = $raw['log_civicrm_entity_log_date'] ?? NULL;
      $logTypeKey = $raw['log_civicrm_entity_log_type'] ?? NULL;
      $entityId = $raw['log_civicrm_entity_id'] ?? NULL;
      $userId = $raw['log_civicrm_entity_log_user_id'] ?? NULL;
      $alteredContactId = $raw['log_civicrm_entity_altered_contact_id'] ?? NULL;
      $action = $raw['log_civicrm_entity_log_action'] ?? '';
      $isDeleted = !empty($raw['log_civicrm_entity_is_deleted']);

      if ($isDeleted && $action === 'Update') {
        $action = E::ts('Delete (to trash)');
      }
      if ($report->getLogTypeRaw($logTypeKey) === 'Contact' && $action === 'Insert') {
        $action = 'Update';
      }
      $newAction = $report->getEntityAction($entityId, $connId, $logTypeKey, $action);
      if ($newAction) {
        $action = $newAction;
      }

      $componentLabel = $report->getLogType($logTypeKey);
      $bracket = (string) $report->getEntityValue($entityId, $logTypeKey, $logDate);

      $key = $logDate . '|' . $componentLabel . '|' . $connId . '|' . $userId . '|' . $alteredContactId;
      if (isset($deduped[$key])) {
        continue;
      }

      $tables = array_values($tablesByType[$componentLabel] ?? []);
      $diffs = $differ->getDiffRows((string) $connId, $logDate, $contactId, $tables);

      $deduped[$key] = [
        'action' => $action,
        'action_slug' => self::slug($action),
        'component' => $componentLabel,
        'component_slug' => self::slug($componentLabel),
        'when' => self::formatWhen($logDate),
        'altered_contact' => $raw['log_civicrm_entity_altered_contact'] ?? '',
        'altered_contact_id' => $alteredContactId,
        'bracket' => $bracket,
        'altered_by' => $raw['altered_by_contact_display_name'] ?? '',
        'altered_by_id' => $userId,
        'diffs' => $diffs,
        'summary' => $this->summarise($diffs),
      ];
    }

    // Most recent first.
    krsort($deduped);
    $rows = array_values($deduped);

    $actionOptions = $componentOptions = [];
    foreach ($rows as $row) {
      $actionOptions[$row['action_slug']] = $row['action'];
      $componentOptions[$row['component_slug']] = $row['component'];
    }
    asort($actionOptions);
    asort($componentOptions);

    return [$rows, $actionOptions, $componentOptions];
  }

  /**
   *
   * @param array $diffs
   *
   * @return string
   */
  protected function summarise(array $diffs): string {
    if (empty($diffs)) {
      return '';
    }
    $names = [];
    foreach ($diffs as $diff) {
      $field = trim(preg_replace('/\s*\(id:\s*\d+\)\s*$/', '', $diff['field']));
      if ($field !== '' && !in_array($field, $names, TRUE)) {
        $names[] = $field;
      }
    }
    if (empty($names)) {
      return '';
    }
    $shown = array_slice($names, 0, self::SUMMARY_FIELD_LIMIT);
    $summary = implode(', ', $shown);
    $extra = count($names) - count($shown);
    if ($extra > 0) {
      $summary .= ' ' . E::ts('+%1 more', [1 => $extra]);
    }
    return $summary;
  }

  /**
   *
   * @param string|null $logDate
   *
   * @return string
   */
  protected static function formatWhen(?string $logDate): string {
    if (empty($logDate)) {
      return '';
    }
    return CRM_Utils_Date::customFormat($logDate);
  }

  /**
   *
   * @param string $label
   *
   * @return string
   */
  protected static function slug(string $label): string {
    return preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($label)));
  }

}
