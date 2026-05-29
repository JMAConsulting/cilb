<?php

use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

class CRM_ChangeNotificationReceipt_Watcher {

  public static function config(): array {
    return [
      'Individual' => [
        'table' => 'civicrm_contact',
        'contact' => TRUE,
        'ops' => ['edit'],
        'fields' => [
          'prefix_id' => 'Prefix',
          'first_name' => 'First Name',
          'middle_name' => 'Middle Name',
          'last_name' => 'Last Name',
          'suffix_id' => 'Suffix',
          'birth_date' => 'Birth Date',
          'gender_id' => 'Gender',
        ],
      ],
      'Email' => [
        'table' => 'civicrm_email',
        'contact' => FALSE,
        'ops' => ['create', 'edit', 'delete'],
        'fields' => ['email' => 'Email'],
      ],
      'Phone' => [
        'table' => 'civicrm_phone',
        'contact' => FALSE,
        'ops' => ['create', 'edit', 'delete'],
        'fields' => ['phone' => 'Phone'],
      ],
      'Address' => [
        'table' => 'civicrm_address',
        'contact' => FALSE,
        'ops' => ['create', 'edit', 'delete'],
        'fields' => [
          'street_address' => 'Street Address',
          'supplemental_address_1' => 'Address (Line 2)',
          'supplemental_address_2' => 'Address (Line 3)',
          'city' => 'City',
          'state_province_id' => 'State/Province',
          'postal_code' => 'Postal Code',
          'country_id' => 'Country',
        ],
      ],
      'Participant' => [
        'table' => 'civicrm_participant',
        'contact' => FALSE,
        'ops' => ['create', 'edit', 'delete'],
        'fields' => [
          'event_id' => 'Exam',
          'status_id' => 'Registration Status',
          'register_date' => 'Registration Date',
          'fee_amount' => 'Fee',
        ],
      ],
      'Contribution' => [
        'table' => 'civicrm_contribution',
        'contact' => FALSE,
        'ops' => ['create', 'edit', 'delete'],
        'fields' => [
          'financial_type_id' => 'Financial Type',
          'total_amount' => 'Amount',
          'contribution_status_id' => 'Payment Status',
          'receive_date' => 'Received Date',
          'trxn_id' => 'Transaction ID',
        ],
      ],
    ];
  }

  protected static function normalise(string $objectName): string {
    if (in_array($objectName, ['Contact', 'Individual', 'Organization'], TRUE)) {
      return 'Individual';
    }
    return $objectName;
  }

  public static function pre(string $op, string $objectName, $id, array &$params): void {
    $name = self::normalise($objectName);
    $config = self::config();
    if (!isset($config[$name]) || !in_array($op, $config[$name]['ops'], TRUE)) {
      return;
    }
    if ($op === 'create' || empty($id)) {
      return;
    }
    $row = self::loadRow($config[$name], (int) $id);
    if ($row !== NULL) {
      \Civi::$statics[__CLASS__]['snapshot']["{$name}:{$id}"] = $row;
    }
  }

  public static function post(string $op, string $objectName, $objectId, &$objectRef): void {
    $name = self::normalise($objectName);
    $config = self::config();
    if (!isset($config[$name]) || !in_array($op, $config[$name]['ops'], TRUE) || empty($objectId)) {
      return;
    }
    $def = $config[$name];
    $objectId = (int) $objectId;
    $snapshotKey = "{$name}:{$objectId}";
    $before = \Civi::$statics[__CLASS__]['snapshot'][$snapshotKey] ?? NULL;
    unset(\Civi::$statics[__CLASS__]['snapshot'][$snapshotKey]);

    if ($op === 'delete') {
      if ($before === NULL) {
        return;
      }
      $contactId = (int) ($before['contact_id'] ?? 0);
      $changes = [];
      foreach ($def['fields'] as $field => $label) {
        if (($before[$field] ?? NULL) !== NULL && $before[$field] !== '') {
          $changes[] = [
            'label' => $label,
            'old' => self::displayValue($name, $field, $before[$field]),
            'new' => '',
          ];
        }
      }
      CRM_ChangeNotificationReceipt_Queue::add($contactId, $def['table'], $objectId, 'delete', $changes);
      return;
    }

    $after = self::loadRow($def, $objectId);
    if ($after === NULL) {
      return;
    }
    $contactId = (int) ($after['contact_id'] ?? ($def['contact'] ? $objectId : 0));

    $changes = [];
    foreach ($def['fields'] as $field => $label) {
      $oldRaw = $op === 'edit' ? ($before[$field] ?? NULL) : NULL;
      $newRaw = $after[$field] ?? NULL;
      if (self::normaliseScalar($oldRaw) === self::normaliseScalar($newRaw)) {
        continue;
      }
      if ($op === 'create' && (self::normaliseScalar($newRaw) === '')) {
        continue;
      }
      $changes[] = [
        'label' => $label,
        'old' => $oldRaw === NULL ? '' : self::displayValue($name, $field, $oldRaw),
        'new' => self::displayValue($name, $field, $newRaw),
      ];
    }

    CRM_ChangeNotificationReceipt_Queue::add($contactId, $def['table'], $objectId, $op, $changes);
  }

  public static function custom(string $op, $groupID, $entityID, array &$params): void {
    if (!in_array($op, ['create', 'edit', 'delete'], TRUE) || empty($entityID) || empty($params)) {
      return;
    }
    $first = reset($params);
    $entityTable = $first['entity_table'] ?? 'civicrm_contact';
    $contactId = self::resolveCustomContactId($entityTable, (int) $entityID);
    if (!$contactId) {
      return;
    }

    $changes = [];
    foreach ($params as $param) {
      $fieldId = $param['custom_field_id'] ?? NULL;
      if (!$fieldId) {
        continue;
      }
      $label = self::customFieldLabel((int) $fieldId);
      $value = $param['value'] ?? '';
      $changes[] = [
        'label' => $label,
        'old' => '',
        'new' => self::customDisplayValue((int) $fieldId, $value),
      ];
    }
    if ($changes) {
      CRM_ChangeNotificationReceipt_Queue::add($contactId, $entityTable, (int) $entityID, $op, $changes);
    }
  }

  protected static function loadRow(array $def, int $id): ?array {
    $cols = array_keys($def['fields']);
    if (!$def['contact']) {
      $cols[] = 'contact_id';
    }
    $cols = array_unique($cols);
    $select = implode(', ', array_map(static fn($c) => "`{$c}`", $cols));
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT {$select} FROM `{$def['table']}` WHERE id = %1",
      [1 => [$id, 'Integer']]
    );
    if (!$dao->fetch()) {
      return NULL;
    }
    $row = [];
    foreach ($cols as $c) {
      $row[$c] = $dao->$c ?? NULL;
    }
    if ($def['contact']) {
      $row['contact_id'] = $id;
    }
    return $row;
  }

  protected static function resolveCustomContactId(string $entityTable, int $entityID): int {
    switch ($entityTable) {
      case 'civicrm_contact':
        return $entityID;

      case 'civicrm_participant':
      case 'civicrm_contribution':
        $table = $entityTable;
        $cid = CRM_Core_DAO::singleValueQuery(
          "SELECT contact_id FROM `{$table}` WHERE id = %1",
          [1 => [$entityID, 'Integer']]
        );
        return (int) $cid;

      default:
        return 0;
    }
  }

  protected static function normaliseScalar($value): string {
    if ($value === NULL) {
      return '';
    }
    return trim((string) $value);
  }

  protected static function displayValue(string $name, string $field, $value): string {
    $value = self::normaliseScalar($value);
    if ($value === '') {
      return '';
    }
    try {
      switch ("{$name}.{$field}") {
        case 'Individual.prefix_id':
        case 'Individual.suffix_id':
        case 'Individual.gender_id':
          return (string) CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', $field, $value);

        case 'Individual.birth_date':
        case 'Participant.register_date':
        case 'Contribution.receive_date':
          return CRM_Utils_Date::customFormat($value);

        case 'Address.state_province_id':
          return (string) CRM_Core_PseudoConstant::stateProvince($value);

        case 'Address.country_id':
          return (string) CRM_Core_PseudoConstant::country($value);

        case 'Participant.event_id':
          return (string) (CRM_Core_DAO::singleValueQuery(
            'SELECT title FROM civicrm_event WHERE id = %1',
            [1 => [$value, 'Integer']]
          ) ?? $value);

        case 'Participant.status_id':
          return (string) CRM_Core_PseudoConstant::getLabel('CRM_Event_DAO_Participant', 'status_id', $value);

        case 'Participant.role_id':
          $parts = explode(CRM_Core_DAO::VALUE_SEPARATOR, trim($value, CRM_Core_DAO::VALUE_SEPARATOR));
          $labels = array_filter(array_map(
            static fn($v) => CRM_Core_PseudoConstant::getLabel('CRM_Event_DAO_Participant', 'role_id', $v),
            array_filter($parts, 'strlen')
          ));
          return implode(', ', $labels) ?: $value;

        case 'Participant.fee_amount':
        case 'Contribution.total_amount':
          return CRM_Utils_Money::format($value);

        case 'Contribution.financial_type_id':
          return (string) CRM_Core_PseudoConstant::getLabel('CRM_Contribute_DAO_Contribution', 'financial_type_id', $value);

        case 'Contribution.contribution_status_id':
          return (string) CRM_Core_PseudoConstant::getLabel('CRM_Contribute_DAO_Contribution', 'contribution_status_id', $value);

        default:
          return $value;
      }
    }
    catch (\Throwable $e) {
      return $value;
    }
  }

  protected static function customFieldLabel(int $fieldId): string {
    $cache = &\Civi::$statics[__CLASS__]['customLabels'];
    if (isset($cache[$fieldId])) {
      return $cache[$fieldId];
    }
    $row = CRM_Core_DAO::executeQuery(
      'SELECT f.label AS field_label, g.title AS group_title
       FROM civicrm_custom_field f
       JOIN civicrm_custom_group g ON g.id = f.custom_group_id
       WHERE f.id = %1',
      [1 => [$fieldId, 'Integer']]
    );
    $label = "Custom field #{$fieldId}";
    if ($row->fetch()) {
      $label = trim(($row->group_title ? $row->group_title . ': ' : '') . $row->field_label);
    }
    return $cache[$fieldId] = $label;
  }

  protected static function customDisplayValue(int $fieldId, $value): string {
    if (is_array($value)) {
      $value = implode(', ', $value);
    }
    $value = self::normaliseScalar($value);
    if ($value === '') {
      return '';
    }
    try {
      $display = CRM_Core_BAO_CustomField::displayValue($value, $fieldId);
      if ($display !== NULL && $display !== '') {
        return strip_tags((string) $display);
      }
    }
    catch (\Throwable $e) {
    }
    return $value;
  }

}
