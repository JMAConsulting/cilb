<?php
namespace Civi\Api4\Action\PriceFieldValue;

use Civi\Api4\PriceField;
use Civi\Api4\PriceFieldValue;

/**
 * This API Action gets the default price_field_value_id for a contribution
 *
 */
class GetDefaultPriceFieldValueForContributionMJW extends \Civi\Api4\Generic\AbstractQueryAction {

  /**
   *
   *  Get the default price_field_id and price_field_value_id for a contribution
   *
   * $result = ['price_field_id' = X, 'price_field_value_id' = Y, 'label' = price_field_value.label]
   *
   * Note that the result class is that of the annotation below, not the hint
   * in the method (which must match the parent class)
   *
   * @var \Civi\Api4\Generic\Result $result
   */
  public function _run(\Civi\Api4\Generic\Result $result) {
    $priceSetName = 'default_contribution_amount';
    $priceField = PriceField::get(FALSE)
      ->addWhere('price_set_id:name', '=', $priceSetName)
      ->addOrderBy('id', 'ASC')
      ->execute()
      ->first();
    // Now get the relevant PriceFieldValue for the MembershipType.
    $priceFieldValue = PriceFieldValue::get(FALSE)
      ->addWhere('price_field_id', '=', $priceField['id'])
      ->execute()
      ->first();
    $results = [
      'price_field_id' => $priceField['id'],
      'price_field_value_id' => $priceFieldValue['id'],
      'label' => $priceFieldValue['label'],
    ];

    $result->exchangeArray($results);
    return $result;
  }

}
