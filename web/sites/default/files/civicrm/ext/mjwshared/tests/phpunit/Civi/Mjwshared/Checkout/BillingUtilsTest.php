<?php

use Civi\Api4\Address;
use Civi\Api4\Contact;
use Civi\Api4\Email;
use Civi\Mjwshared\Checkout\BillingUtils;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;

/**
 * @group headless
 */
class BillingUtilsTest extends CiviUnitTestCase implements HeadlessInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    $this->useTransaction();
  }

  public function testGetBillingAddressReturnsEmptyArrayWhenNoneFound(): void {
    $contactID = $this->individualCreate();

    $this->assertSame([], BillingUtils::getBillingAddress(NULL, $contactID));
    $this->assertSame([], BillingUtils::getBillingAddress(999999));
    $this->assertSame([], BillingUtils::getBillingAddress());
  }

  public function testGetBillingAddressPrefersBillingOverPrimary(): void {
    $contactID = $this->individualCreate();
    $this->createAddress($contactID, 'Primary Street', ['is_primary' => TRUE]);
    $billingID = $this->createAddress($contactID, 'Billing Street', ['is_primary' => FALSE, 'is_billing' => TRUE]);

    $address = BillingUtils::getBillingAddress(NULL, $contactID);
    $this->assertSame('Billing Street', $address['street_address']);
    $this->assertSame('Billing Street', $address['billingStreetAddress']);
    $this->assertSame('US', $address['billingCountry']);

    $this->assertSame('Billing Street', BillingUtils::getBillingAddress($billingID)['street_address']);
  }

  public function testGetBillingAddressFallsBackToPrimary(): void {
    $contactID = $this->individualCreate();
    $this->createAddress($contactID, 'Primary Street', ['is_primary' => TRUE]);

    $this->assertSame('Primary Street', BillingUtils::getBillingAddress(NULL, $contactID)['billingStreetAddress']);
  }

  public function testGetBillingEmail(): void {
    $contactID = $this->individualCreate(['email' => 'primary@example.org']);
    $this->assertSame('primary@example.org', BillingUtils::getBillingEmail($contactID));

    Email::create(FALSE)
      ->setValues(['contact_id' => $contactID, 'email' => 'billing@example.org', 'is_billing' => TRUE, 'location_type_id:name' => 'Billing'])
      ->execute();
    $this->assertSame('billing@example.org', BillingUtils::getBillingEmail($contactID));

    $noEmailContactID = Contact::create(FALSE)
      ->setValues(['contact_type' => 'Individual', 'first_name' => 'No', 'last_name' => 'Email'])
      ->execute()
      ->first()['id'];
    $this->assertSame('', BillingUtils::getBillingEmail($noEmailContactID));
  }

  private function createAddress(int $contactID, string $street, array $values): int {
    return Address::create(FALSE)
      ->setValues($values + [
        'contact_id' => $contactID,
        'location_type_id:name' => 'Billing',
        'street_address' => $street,
        'city' => 'Example City',
        'postal_code' => '12345',
        'country_id:abbr' => 'US',
      ])
      ->execute()
      ->first()['id'];
  }

}
