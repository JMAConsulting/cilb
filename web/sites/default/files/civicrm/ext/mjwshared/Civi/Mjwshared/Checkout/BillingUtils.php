<?php

namespace Civi\Mjwshared\Checkout;

/**
 * Standard billing-detail lookups for Afform CheckoutOptions.
 *
 * Payment processors driving an Afform checkout need a consistent way to
 * resolve the billing email and billing address for a contact/address once
 * the contact, address and contribution have been saved. CiviCRM core is
 * adding equivalent helpers to \Civi\Checkout\CheckoutOptionUtils, but they
 * are not yet available in a released core, so they live here in the meantime
 * to give dependent extensions (e.g. authnet) a stable home to call.
 *
 * When a core release provides these helpers, callers should switch to the
 * core class and this file can be removed.
 */
class BillingUtils {

  /**
   * Optional helper function to get the billing email from a contact.
   *
   * @param int $contactID
   *
   * @return string
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function getBillingEmail(int $contactID): string {
    if ($contactID) {
      $email = \Civi\Api4\Email::get(FALSE)
        ->addSelect('email')
        ->addWhere('contact_id', '=', $contactID)
        ->addWhere('is_billing', '=', TRUE)
        ->execute()
        ->first();
      if (!$email) {
        $email = \Civi\Api4\Email::get(FALSE)
          ->addSelect('email')
          ->addWhere('contact_id', '=', $contactID)
          ->addWhere('is_primary', '=', TRUE)
          ->execute()
          ->first();
      }
    }
    return $email['email'] ?? '';
  }

  /**
   * Helper function to provide a standard way of getting billing address
   * CheckoutOptions can optionally use this to retrieve the billing address
   *   in a standard way from Afform.
   * It is assumed that by the time this function is called contacts, addresses and contributions
   *   have been saved. So we can look up the address either by contactID or directly by addressID.
   * A contribution a billing address not linked to a contact via Contribution.address_id
   *
   * @param int|null $addressID
   * @param int|null $contactID
   *
   * @return array
   *   Empty array if no address is found.
   *   Returns API4 style ($api4AddressFields) + propertyBag style ($propertyBagAddressParams) fields.
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function getBillingAddress(?int $addressID = NULL, ?int $contactID = NULL): array {
    $api4AddressFields = [
      'street_address',
      'city',
      'state_province_id',
      'state_province_id:abbr',
      'state_province_id:label',
      'country_id',
      'country_id:abbr',
      'country_id:label',
      'postal_code',
    ];

    // Map API4 => propertyBag
    $propertyBagAddressFieldMap = [
      'street_address' => 'billingStreetAddress',
      'city' => 'billingCity',
      'state_province_id:abbr' => 'billingStateProvince',
      'postal_code' => 'billingPostalCode',
      'country_id:abbr' => 'billingCountry',
    ];

    $address = [];
    if ($addressID) {
      // APIv4 Address is keyed on 'id'; there is no 'address_id' field to filter on.
      $address = \Civi\Api4\Address::get(FALSE)
        ->setSelect($api4AddressFields)
        ->addWhere('id', '=', $addressID)
        ->execute()
        ->first();
    }
    elseif ($contactID) {
      $address = \Civi\Api4\Address::get(FALSE)
        ->setSelect($api4AddressFields)
        ->addWhere('contact_id', '=', $contactID)
        ->addWhere('is_billing', '=', TRUE)
        ->execute()
        ->first();
      if (!$address) {
        $address = \Civi\Api4\Address::get(FALSE)
          ->setSelect($api4AddressFields)
          ->addWhere('contact_id', '=', $contactID)
          ->addWhere('is_primary', '=', TRUE)
          ->execute()
          ->first();
      }
    }
    if (!$address) {
      return [];
    }
    foreach ($propertyBagAddressFieldMap as $api4 => $propertyBag) {
      $address[$propertyBag] = $address[$api4] ?? '';
    }
    return $address;
  }

  /**
   * Optional helper to get the billing name for a contact.
   * CheckoutOptions can use this to populate the billing name on the payment.
   * The billing-address helper intentionally returns address fields only, so
   * the name is fetched separately here.
   *
   * @param int $contactID
   *
   * @return array
   *   PropertyBag-style keys (firstName, lastName). Empty array if no contact.
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function getBillingName(int $contactID): array {
    $name = [];
    if ($contactID) {
      $contact = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('first_name', 'last_name')
        ->addWhere('id', '=', $contactID)
        ->execute()
        ->first();
      if ($contact) {
        $name['firstName'] = $contact['first_name'] ?? '';
        $name['lastName'] = $contact['last_name'] ?? '';
      }
    }
    return $name;
  }

}
