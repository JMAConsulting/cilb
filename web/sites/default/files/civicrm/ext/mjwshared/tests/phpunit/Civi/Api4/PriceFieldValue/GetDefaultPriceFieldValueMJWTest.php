<?php

use CRM_Mjwshared_ExtensionUtil as E;
use Civi\Api4\Contribution;
use Civi\Api4\LineItem;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;

/**
 * Test the API4 ContributionRecur.UpdateAmountOnRecurMJW
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class GetDefaultPriceFieldValueMJWTest extends CiviUnitTestCase implements HeadlessInterface, HookInterface {

  /**
   * Setup used when HeadlessInterface is implemented.
   *
   * Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
   *
   * @link https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
   *
   * @return \Civi\Test\CiviEnvBuilder
   *
   * @throws \CRM_Extension_Exception_ParseException
   */
  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp():void {
    parent::setUp();
  }

  public function tearDown():void {
    $this->quickCleanUpFinancialEntities();
    $this->quickCleanup(['civicrm_contact']);
    parent::tearDown();
  }

  /**
   * Test that the lineitem has the same values as the defaults retrieved by API
   */
  public function testGetDefaultPriceFieldValueForContribution(): void {
    $cid = $this->individualCreate();
    $contribution = Contribution::create(FALSE)
      ->addValue('contact_id', $cid)
      ->addValue('total_amount', 5)
      ->addValue('financial_type_id', 1)
      ->execute()
      ->first();
    $lineItem = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $contribution['id'])
      ->execute()
      ->first();

    $priceFieldValueForContribution = \Civi\Api4\PriceFieldValue::getDefaultPriceFieldValueForContributionMJW(FALSE)
      ->execute();

    $this->assertEquals($lineItem['price_field_id'], $priceFieldValueForContribution['price_field_id']);
    $this->assertEquals($lineItem['price_field_value_id'], $priceFieldValueForContribution['price_field_value_id']);
    $this->assertEquals($lineItem['label'], $priceFieldValueForContribution['label']);
  }

  /**
   * Test that the lineitem has the same values as the defaults retrieved by API
   */
  public function testGetDefaultPriceFieldValueForMembership(): void {
    $cid = $this->individualCreate();
    $this->membershipTypeCreate(['name' => 'General']);

    // As this is a test we want to get the (newly created) default pricefieldvalueid
    // for the membership. In order to do it a different way from the API we are testing
    // we'll just grab the latest by ID (because in this test case it will match the membership above).
    $priceFieldValue = \Civi\Api4\PriceFieldValue::get(FALSE)
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    $orderCreateParams = [
      'total_amount'           => 5,
      'contact_id'             => $cid,
      'financial_type_id'      => 'Member Dues',
      'contribution_status_id' => 'Pending',
    ];
    $lineItemParams = [
      'membership_type_id' => 'General',
      'contact_id' => $cid,
      'status_id' => 'Pending',
    ];
    $lineItem = [
      'line_total' => 5,
      'unit_price' => 5,
      'price_field_id' => $priceFieldValue['id'],
      'price_field_value_id' => $priceFieldValue['price_field_id'],
      // 'financial_type_id' => $this->getFinancialTypeID(),
      'qty' => 1,
      'entity_table' => 'civicrm_membership',
    ];

    $orderCreateParams['line_items'] = [
      [
        'params' => $lineItemParams,
        'line_item' => [$lineItem]
      ]
    ];
    $contribution = civicrm_api3('Order', 'create', $orderCreateParams);

    $lineItemResult = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $contribution['id'])
      ->execute()
      ->first();
    $mmembershipID = $lineItemResult['entity_id'];
    $priceFieldValueForMembership = \Civi\Api4\PriceFieldValue::getDefaultPriceFieldValueForMembershipMJW(FALSE)
      ->setMembershipID($mmembershipID)
      ->execute();

    $this->assertEquals($lineItemResult['price_field_id'], $priceFieldValueForMembership['price_field_id']);
    $this->assertEquals($lineItemResult['price_field_value_id'], $priceFieldValueForMembership['price_field_value_id']);
    $this->assertEquals($lineItemResult['label'], $priceFieldValueForMembership['label']);
  }

}