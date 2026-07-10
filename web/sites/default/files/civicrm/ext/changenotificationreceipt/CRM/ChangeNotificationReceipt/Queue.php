<?php

use Civi\Api4\ChangeNotificationQueue;
use CRM_ChangeNotificationReceipt_ExtensionUtil as E;

class CRM_ChangeNotificationReceipt_Queue {

  const STATUS_PENDING = 'pending';
  const STATUS_SENT = 'sent';
  const STATUS_SKIPPED = 'skipped';
  const STATUS_ERROR = 'error';

  /**
   *
   * @param int $contactId
   * @param string $entityTable
   * @param int|null $entityId
   * @param string $action create|edit|delete
   * @param array $changes List of ['label' => , 'old' => , 'new' => ].
   */
  public static function add(int $contactId, string $entityTable, ?int $entityId, string $action, array $changes): void {
    if (!$contactId) {
      return;
    }
    if ($action === 'edit' && empty($changes)) {
      return;
    }
    ChangeNotificationQueue::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('entity_table', $entityTable)
      ->addValue('entity_id', $entityId ?: NULL)
      ->addValue('action', $action)
      ->addValue('changes', $changes)
      ->addValue('status', self::STATUS_PENDING)
      ->execute();
  }

  /**
   *
   * @return int[]
   */
  public static function getPendingContactIds(): array {
    return (array) ChangeNotificationQueue::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('status', '=', self::STATUS_PENDING)
      ->addGroupBy('contact_id')
      ->execute()
      ->column('contact_id');
  }

  /**
   *
   * @param int $contactId
   * @return array[]
   */
  public static function getPendingForContact(int $contactId): array {
    return (array) ChangeNotificationQueue::get(FALSE)
      ->addSelect('id', 'entity_table', 'entity_id', 'action', 'changes', 'created_date')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('status', '=', self::STATUS_PENDING)
      ->addOrderBy('created_date', 'ASC')
      ->addOrderBy('id', 'ASC')
      ->execute()
      ->getArrayCopy();
  }

  /**
   *
   * @param int[] $ids
   * @param string $status
   */
  public static function markProcessed(array $ids, string $status): void {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
      return;
    }
    ChangeNotificationQueue::update(FALSE)
      ->addWhere('id', 'IN', $ids)
      ->addValue('status', $status)
      ->addValue('processed_date', date('Y-m-d H:i:s'))
      ->execute();
  }

}
