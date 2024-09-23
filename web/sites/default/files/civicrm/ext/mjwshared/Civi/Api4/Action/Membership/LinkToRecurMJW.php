<?php
namespace Civi\Api4\Action\Membership;

/**
 * This API Action updates the contributionRecur and related entities (templatecontribution/lineitems)
 *   when a subscription is changed so they are not linked to a Membership.
 *
 */
class LinkToRecurMJW extends \Civi\Api4\Generic\AbstractUpdateAction {

  /**
   * @var array field => REQUIRED
   */
  private $whereFields = [
    'id' => TRUE,
    'contribution_recur_id' => FALSE,
    'contribution_id' => FALSE,
  ];

  /**
   *
   * Note that the result class is that of the annotation below, not the hint
   * in the method (which must match the parent class)
   *
   * @var \Civi\Api4\Generic\Result $result
   */
  public function _run(\Civi\Api4\Generic\Result $result) {
    if (empty($this->values['id'])) {
      throw new \CRM_Core_Exception('Must specify Membership ID (id)');
    }
    // One of contribution_recur_id or contribution_id is required.
    if (empty($this->values['contribution_recur_id']) && (empty($this->values['contribution_id']))) {
      throw new \CRM_Core_Exception('One of contribution_id or contribution_recur_id is required');
    }

    $membershipProcessor = new \Civi\MJW\Payment\Membership();
    $entityIDs = $membershipProcessor->linkMembershipToRecur($this->values['id'], $this->values['contribution_id'] ?? NULL, $this->values['contribution_recur_id'] ?? NULL);

    $result->exchangeArray($entityIDs);
    return $result;
  }

  protected function updateRecords(array $items): array {
    return $items;
  }
}
