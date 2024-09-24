<?php
namespace Civi\Api4\Action\PriceFieldValue;

use Civi\Api4\PriceField;
use Civi\Api4\PriceFieldValue;

/**
 * This API Action gets the default price_field_value_id for the specified membership
 *
 * @property int $membershipID
 */
class GetDefaultPriceFieldValueForMembershipMJW extends \Civi\Api4\Generic\AbstractAction {

  /**
   * The Membership ID
   *
   * @var int
   * @required
   */
  protected int $membershipID = 0;

  /**
   *
   * Get the default price_field_id and price_field_value_id for the membership
   *
   * $result = ['price_field_id' = X, 'price_field_value_id' = Y, 'label' = price_field_value.label]
   *
   * Note that the result class is that of the annotation below, not the hint
   * in the method (which must match the parent class)
   *
   * @var \Civi\Api4\Generic\Result $result
   */
  public function _run(\Civi\Api4\Generic\Result $result) {
    if (empty($this->membershipID)) {
      throw new \CRM_Core_Exception('Membership ID is required');
    }
    // First we need membership_type_id.member_of_contact_id and membership_type_id to find the PriceFieldValue.
    $membership = \Civi\Api4\Membership::get(FALSE)
      ->addSelect('membership_type_id.member_of_contact_id', 'membership_type_id')
      ->addWhere('id', '=', $this->membershipID)
      ->execute()
      ->first();

    // PriceFields and Membership Types...
    // There is a default PriceSet for contributions with name="default_contribution_amount"
    // There is a default PriceSet for memberships with name="default_membership_type_amount"
    // Each MembershipType has a "member_of_contact_id" which gets used as a FK reference in PriceField.name
    // Each MembershipType has a PriceFieldValue with PriceFieldValue.membership_type_id = Membership Type
    // Why all this? Well we could (and probably do) have multiple PriceSets that include the same MembershipType but
    //   different prices. But only one of them will be linked to the default PriceSet.
    // Confused yet?

    // Get the default price field ID for memberships from the default membership PriceSet and the member_of_contact_id
    $priceField = PriceField::get(FALSE)
      ->addWhere('price_set_id:name', '=', 'default_membership_type_amount')
      ->addWhere('name', '=', $membership['membership_type_id.member_of_contact_id'])
      ->addOrderBy('id', 'ASC')
      ->execute()
      ->first();

    // Now get the relevant PriceFieldValue for the MembershipType.
    $priceFieldValue = PriceFieldValue::get(FALSE)
      ->addWhere('price_field_id', '=', $priceField['id'])
      ->addWhere('membership_type_id', '=', $membership['membership_type_id'])
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
