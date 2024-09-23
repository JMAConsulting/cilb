<?php

namespace Civi\MJW\Payment;

use Civi\Api4\Contribution;
use Civi\Api4\ContributionRecur;
use Civi\Api4\LineItem;
use Civi\Core\Service\AutoService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 * @service
 */
class Membership extends AutoService implements EventSubscriberInterface {

  /**
   * @return array
   */
  public static function getSubscribedEvents() {
    return [
      'hook_civicrm_pre' => ['on_hook_civicrm_pre', 150],
    ];
  }

  /**
   * Force setting entity_table / entity_id in LineItem.create (for update)
   *
   * @param \Civi\Core\Event\PreEvent $event
   *
   * @return void
   */
  public function on_hook_civicrm_pre(\Civi\Core\Event\PreEvent $event) {
    if ($event->entity !== 'LineItem' || $event->action !== 'edit') {
      return;
    }
    // API3 LineItem.create removes entity_table/entity_id before saving.
    // We add them after LineItem.create removed them but before save!
    if (isset($event->params['entity_table_force'])) {
      $event->params['entity_table'] = $event->params['entity_table_force'];
      unset($event->params['entity_table_force']);
    }
    if (isset($event->params['entity_id_force'])) {
      $event->params['entity_id'] = $event->params['entity_id_force'];
      unset($event->params['entity_id_force']);
    }
  }

  /**
   * Update and Link Membership to Contribution LineItem and Recur
   *
   * @param int $membershipID
   * @param int|null $contributionID
   * @param int|null $contributionRecurID
   *
   * @return array The various entity IDs
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function linkMembershipToRecur(int $membershipID, $contributionID = NULL, $contributionRecurID = NULL): array {
    // Get contribution ID from the recur if possible
    if (empty($contributionID) && !empty($contributionRecurID)) {
      $contributionID = \CRM_Contribute_BAO_ContributionRecur::ensureTemplateContributionExists($contributionRecurID);
    }

    // First the simple bit! Link the membership to the contributionRecur record if we have one
    if ($contributionRecurID) {
      \Civi\Api4\Membership::update(FALSE)
        ->addWhere('id', '=', $membershipID)
        ->addValue('contribution_recur_id', $contributionRecurID)
        ->execute()
        ->first();
    }

    // Now we need to update the LineItems on the contribution so that renewals etc. work properly.
    $priceFieldValues = \Civi\Api4\PriceFieldValue::getDefaultPriceFieldValueForMembershipMJW(FALSE)
      ->setMembershipID($membershipID)
      ->execute();

    // Now get the LineItems for the contribution
    $lineItems = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $contributionID)
      ->execute();
    if ($lineItems->count() > 1) {
      throw new \CRM_Core_Exception('ContributionID: ' . $contributionID . ' has more than one lineitem. Not linking membership as I don\'t know what to do');
      // @todo: Maybe search them for a LineItem matching this membership type?
    }

    // Get the membership FinancialType so we can update the Contribution,Recur and LineItem.
    $membership = \Civi\Api4\Membership::get(FALSE)
      ->addSelect('membership_type_id.financial_type_id')
      ->addWhere('id', '=', $membershipID)
      ->execute()
      ->first();

    // Update the contributionRecur with the new FinancialType
    if (!empty($contributionRecurID)) {
      ContributionRecur::update(FALSE)
        ->addWhere('id', '=', $contributionRecurID)
        ->addValue('financial_type_id', $membership['membership_type_id.financial_type_id'])
        ->execute();
    }
    // Update the contribution with the new FinancialType
    if (!empty($contributionID)) {
      Contribution::update(FALSE)
        ->addWhere('id', '=', $contributionID)
        ->addValue('financial_type_id', $membership['membership_type_id.financial_type_id'])
        ->execute();
    }

    // Finally, Update the LineItem to map to a membership
    civicrm_api3('LineItem', 'create', [
      'entity_id' => $membershipID,
      'id' => $lineItems->first()['id'],
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $priceFieldValues['price_field_id'],
      'price_field_value_id' => $priceFieldValues['price_field_value_id'],
      'label' => $priceFieldValues['label'],
      'financial_type_id' => $membership['membership_type_id.financial_type_id'],
      // API3 LineItem.create removes entity_table/entity_id before saving.
      // We add them here so we can add them back in using hook_civicrm_pre (implemented in on_hook_civicrm_pre).
      'entity_table_force' => 'civicrm_membership',
      'entity_id_force' => $membershipID,
    ]);

    return [
      'lineItemID' => $lineItems->first()['id'],
      'contributionRecurID' => $contributionRecurID ?? NULL,
      'contributionID' => $contributionID,
      'membershipID' => $membershipID,
      'action' => 'link',
    ];
  }

  /**
   * Update and Link Membership to Contribution LineItem and Recur
   *
   * @param int $membershipID
   * @param int|null $contributionID
   * @param int|null $contributionRecurID
   *
   * @return array The various entity IDs
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function unlinkMembershipFromRecur(int $membershipID, $contributionID = NULL, $contributionRecurID = NULL): array {
    if (empty($contributionID) && empty($contributionRecurID)) {
      $contributionRecurID = \Civi\Api4\Membership::get(FALSE)
        ->addSelect('contribution_recur_id')
        ->addWhere('id', '=', $membershipID)
        ->execute()
        ->first()['contribution_recur_id'];
      if (empty($contributionRecurID)) {
        throw new \CRM_Core_Exception('Membership ' . $membershipID . ' does not have a contribution_recur_id. Cannot unlink!');
      }
    }

    // Get contribution ID from the recur if possible
    if (empty($contributionID) && !empty($contributionRecurID)) {
      $contributionID = \CRM_Contribute_BAO_ContributionRecur::ensureTemplateContributionExists($contributionRecurID);
    }

    // First the simple bit! Remove the contribution_recur_id from the membership
    if ($contributionRecurID) {
      \Civi\Api4\Membership::update(FALSE)
        ->addWhere('id', '=', $membershipID)
        ->addValue('contribution_recur_id', NULL)
        ->execute()
        ->first();
    }

    // Now we need to update the LineItems on the contribution so that renewals etc. work properly.
    $priceFieldValues = \Civi\Api4\PriceFieldValue::getDefaultPriceFieldValueForContributionMJW(FALSE)->execute();

    // Now get the LineItems for the contribution
    $lineItems = LineItem::get(FALSE)
      ->addWhere('contribution_id', '=', $contributionID)
      ->execute();
    if ($lineItems->count() > 1) {
      throw new \CRM_Core_Exception('ContributionID: ' . $contributionID . ' has more than one lineitem. Not unlinking membership as I don\'t know what to do');
      // @todo: Maybe search them for a LineItem matching this membership type?
    }

    // @todo Maybe change the financial type on contribution/recur to default / "Donation".
    // But not sure what would be best as we don't have an obvious default (when linking we use Membership financialType).

    // Finally, Update the LineItem to map to a contribution
    $newLineItem = civicrm_api3('LineItem', 'create', [
      'entity_id' => $contributionID,
      'id' => $lineItems->first()['id'],
      'entity_table' => 'civicrm_contribution',
      'price_field_id' => $priceFieldValues['price_field_id'],
      'price_field_value_id' => $priceFieldValues['price_field_value_id'],
      'label' => $priceFieldValues['label'],
      // 'financial_type_id' => $membership['membership_type_id.financial_type_id'],
      // API3 LineItem.create removes entity_table/entity_id before saving.
      // We add them here so we can add them back in using hook_civicrm_pre (implemented in on_hook_civicrm_pre).
      'entity_table_force' => 'civicrm_contribution',
      'entity_id_force' => $contributionID,
    ]);

    return [
      'lineItemID' => $lineItems->first()['id'],
      'contributionRecurID' => $contributionRecurID ?? NULL,
      'contributionID' => $contributionID,
      'membershipID' => $membershipID,
      'action' => 'unlink',
    ];
  }

}
