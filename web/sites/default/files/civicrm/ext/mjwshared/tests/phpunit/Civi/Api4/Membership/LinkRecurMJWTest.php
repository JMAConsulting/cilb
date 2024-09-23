<?php

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\LineItem;
use Civi\Api4\Membership;
use Civi\Api4\PriceField;
use Civi\Api4\PriceFieldValue;
use CRM_Mjwshared_ExtensionUtil as E;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

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
class LinkRecurMJWTest extends CiviUnitTestCase implements HeadlessInterface, HookInterface {

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
   * Test that we can link a membership to a recur and that all related entities are correctly updated
   */
  public function testLinkRecur(): void {
    $cid = $this->individualCreate();
    $crid = ContributionRecur::create(FALSE)
      ->addValue('contact_id', $cid)
      ->addValue('amount', 5)
      ->execute()
      ->first()['id'];
    $coid = Contribution::create(FALSE)
      ->addValue('contact_id', $cid)
      ->addValue('total_amount', 5)
      ->addValue('contribution_recur_id', $crid)
      ->addValue('financial_type_id', 1)
      ->execute()
      ->first()['id'];
    $lineItemID = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $coid)
      ->execute()
      ->first()['id'];
    $mid = $this->contactMembershipCreate(['contact_id' => $cid]);

    $priceFieldValueForMembership = PriceFieldValue::getDefaultPriceFieldValueForMembershipMJW(FALSE)
      ->setMembershipID($mid)
      ->execute();

    $actualResult = Membership::linkToRecurMJW(FALSE)
      ->addValue('id', $mid)
      ->addValue('contribution_recur_id', $crid)
      ->execute()
      ->getArrayCopy();

    // Now check results
    // Check that membership now has contribution_recur_id set
    $updatedMembership = Membership::get(FALSE)
      ->addWhere('id', '=', $mid)
      ->execute()
      ->first();
    $this->assertEquals($crid, $updatedMembership['contribution_recur_id']);

    $templateContribution = \CRM_Contribute_BAO_ContributionRecur::getTemplateContribution($crid);
    $this->assertEquals($crid, $templateContribution['contribution_recur_id']);
    $templateContributionlineItem = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $templateContribution['id'])
      ->execute()
      ->first();
    $priceFieldValueForContribution = PriceFieldValue::getDefaultPriceFieldValueForContributionMJW(FALSE)->execute();
    // It would be nice to check the actual values here, but for now let's just check that they are different
    $this->assertNotEquals($priceFieldValueForContribution['price_field_id'], $priceFieldValueForMembership['price_field_id']);
    $this->assertNotEquals($priceFieldValueForContribution['price_field_value_id'], $priceFieldValueForMembership['price_field_value_id']);
    $this->assertNotEquals($priceFieldValueForContribution['label'], $priceFieldValueForMembership['label']);

    $lineItemExpectedActual = [
      'civicrm_membership' => $templateContributionlineItem['entity_table'],
      $mid => $templateContributionlineItem['entity_id'],
    ];
    foreach ($lineItemExpectedActual as $expected => $actual) {
      $this->assertEquals($expected, $actual);
    }

    $expectedResult = [
      'action' => 'link',
      'contributionID' => $templateContribution['id'],
      'contributionRecurID' => $crid,
      'membershipID' => $mid,
      'lineItemID' => $templateContributionlineItem['id'],
    ];
    $this->assertArrayValuesEqual($expectedResult, $actualResult);
  }

  /**
   * Test that we can unlink a membership from a recur and that all related entities are correctly updated
   */
  public function testUnlinkRecur(): void {
    $cid = $this->individualCreate();
    $crid = ContributionRecur::create(FALSE)
      ->addValue('contact_id', $cid)
      ->addValue('amount', 5)
      ->execute()
      ->first()['id'];
    $coid = Contribution::create(FALSE)
      ->addValue('contact_id', $cid)
      ->addValue('total_amount', 5)
      ->addValue('contribution_recur_id', $crid)
      ->addValue('financial_type_id', 1)
      ->execute()
      ->first()['id'];
    $lineItemID = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $coid)
      ->execute()
      ->first()['id'];
    $mid = $this->contactMembershipCreate(['contribution_recur_id' => $crid, 'contact_id' => $cid]);

    $priceFieldValueForMembership = PriceFieldValue::getDefaultPriceFieldValueForMembershipMJW(FALSE)
      ->setMembershipID($mid)
      ->execute();

    $actualResult = Membership::unlinkFromRecurMJW(FALSE)
      ->addValue('id', $mid)
      ->addValue('contribution_recur_id', $crid)
      ->execute()
      ->getArrayCopy();

    // Now check results
    // Check that membership no longer has contribution_recur_id set
    $updatedMembership = Membership::get(FALSE)
      ->addWhere('id', '=', $mid)
      ->execute()
      ->first();
    $this->assertEmpty($updatedMembership['contribution_recur_id']);

    $templateContribution = \CRM_Contribute_BAO_ContributionRecur::getTemplateContribution($crid);
    $this->assertEquals($crid, $templateContribution['contribution_recur_id']);
    $templateContributionlineItem = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $templateContribution['id'])
      ->execute()
      ->first();
    $priceFieldValueForContribution = PriceFieldValue::getDefaultPriceFieldValueForContributionMJW(FALSE)->execute();
    // It would be nice to check the actual values here, but for now let's just check that they are different
    $this->assertNotEquals($priceFieldValueForContribution['price_field_id'], $priceFieldValueForMembership['price_field_id']);
    $this->assertNotEquals($priceFieldValueForContribution['price_field_value_id'], $priceFieldValueForMembership['price_field_value_id']);
    $this->assertNotEquals($priceFieldValueForContribution['label'], $priceFieldValueForMembership['label']);

    $lineItemExpectedActual = [
      'civicrm_contribution' => $templateContributionlineItem['entity_table'],
      $templateContribution['id'] => $templateContributionlineItem['entity_id'],
    ];
    foreach ($lineItemExpectedActual as $expected => $actual) {
      $this->assertEquals($expected, $actual);
    }

    $expectedResult = [
      'action' => 'unlink',
      'contributionID' => $templateContribution['id'],
      'contributionRecurID' => $crid,
      'membershipID' => $mid,
      'lineItemID' => $templateContributionlineItem['id'],
    ];
    $this->assertArrayValuesEqual($expectedResult, $actualResult);
  }

}
