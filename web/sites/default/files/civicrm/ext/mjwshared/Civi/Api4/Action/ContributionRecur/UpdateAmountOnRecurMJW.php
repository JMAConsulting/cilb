<?php
namespace Civi\Api4\Action\ContributionRecur;

use Brick\Money\Money;

/**
 * This API Action updates the contributionRecur and related entities (templatecontribution/lineitems)
 *   when a subscription is changed.
 *
 */
class UpdateAmountOnRecurMJW extends \Civi\Api4\Generic\AbstractUpdateAction {

  /**
   *
   * Note that the result class is that of the annotation below, not the hint
   * in the method (which must match the parent class)
   *
   * @var \Civi\Api4\Generic\Result $result
   */
  public function _run(\Civi\Api4\Generic\Result $result) {
    if (!array_key_exists('amount', $this->values)) {
      throw new \CRM_Core_Exception('Must specify amount');
    }
    foreach ($this->values as $key => $value) {
      if ($key !== 'amount') {
        throw new \CRM_Core_Exception('Only amount can be specified');
      }
    }

    // Load the recurs.
    $recurs = \Civi\Api4\ContributionRecur::get(FALSE)
      ->setWhere($this->where)
      ->execute()->indexBy('id');

    foreach ($recurs as $recur) {
      $subscription = new \Civi\MJW\Payment\Subscription();
      $subscription->setRecur($recur);
      if (empty($subscription->getRecur())) {
        throw new \CRM_Core_Exception('RecurID is not valid!');
      }

      $newAmount = Money::of($this->values['amount'], $recur['currency']);
      $recurResults[] = $subscription->updateRecurAndTemplateContributionAmount($newAmount);
    }
    $result->exchangeArray($recurResults ?? []);
    return $result;
  }

  protected function updateRecords(array $items): array {
    return $items;
  }
}
