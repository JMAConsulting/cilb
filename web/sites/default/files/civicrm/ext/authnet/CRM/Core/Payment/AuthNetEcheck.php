<?php
/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

use Civi\Payment\PropertyBag;
use CRM_AuthNetEcheck_ExtensionUtil as E;
use net\authorize\api\contract\v1 as AnetAPI;

class CRM_Core_Payment_AuthNetEcheck extends CRM_Core_Payment_AuthorizeNetCommon {

  use CRM_Core_Payment_MJWTrait;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct($mode, &$paymentProcessor) {
    parent::__construct($mode, $paymentProcessor);
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeName() {
    return 'direct_debit';
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeLabel() {
    return E::ts('Authorize.net (eCheck.Net)');
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return array
   */
  public function getPaymentFormFields() {
    return [
      'account_holder',
      'bank_account_number',
      'bank_identification_number',
      'bank_name',
    ];
  }

  /**
   * Return an array of all the details about the fields potentially required for payment fields.
   *
   * Only those determined by getPaymentFormFields will actually be assigned to the form
   *
   * @return array
   *   field metadata
   */
  public function getPaymentFormFieldsMetadata() {
    return [
      'account_holder' => [
        'htmlType' => 'text',
        'name' => 'account_holder',
        'title' => E::ts('Name on Account'),
        'attributes' => [
          'size' => 20,
          'maxlength' => 22,
          'autocomplete' => 'on',
        ],
        'is_required' => TRUE,
      ],
      // US account number (max 17 digits)
      'bank_account_number' => [
        'htmlType' => 'text',
        'name' => 'bank_account_number',
        'title' => E::ts('Account Number'),
        'description' => E::ts('Usually between 8 and 12 digits - identifies your individual account'),
        'attributes' => [
          'size' => 20,
          'maxlength' => 17,
          'autocomplete' => 'off',
        ],
        'rules' => [
          [
            'rule_message' => E::ts('Please enter a valid Bank Identification Number (value must not contain punctuation characters).'),
            'rule_name' => 'nopunctuation',
            'rule_parameters' => NULL,
          ],
        ],
        'is_required' => TRUE,
      ],
      'bank_identification_number' => [
        'htmlType' => 'text',
        'name' => 'bank_identification_number',
        'title' => E::ts('Routing Number'),
        'description' => E::ts('A 9-digit code (ABA number) that is used to identify where your bank account was opened (eg. 211287748)'),
        'attributes' => [
          'size' => 20,
          'maxlength' => 9,
          'autocomplete' => 'off',
        ],
        'is_required' => TRUE,
        'rules' => [
          [
            'rule_message' => E::ts('Please enter a valid Bank Identification Number (value must not contain punctuation characters).'),
            'rule_name' => 'nopunctuation',
            'rule_parameters' => NULL,
          ],
        ],
      ],
      'bank_name' => [
        'htmlType' => 'text',
        'name' => 'bank_name',
        'title' => E::ts('Bank Name'),
        'attributes' => [
          'size' => 20,
          'maxlength' => 50,
          'autocomplete' => 'off',
        ],
        'is_required' => TRUE,
      ],
    ];
  }

  /**
   * Get the bank account for AuthNet
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \net\authorize\api\contract\v1\BankAccountType
   */
  private function getBankAccount(PropertyBag $propertyBag) {
    // Create the payment data for a Bank Account
    $bankAccount = new AnetAPI\BankAccountType();
    // see eCheck documentation for proper echeck type to use for each situation
    $bankAccount->setEcheckType('WEB');
    $bankAccount->setRoutingNumber($propertyBag->getCustomProperty('bank_identification_number'));
    $bankAccount->setAccountNumber($propertyBag->getCustomProperty('bank_account_number'));
    $bankAccount->setNameOnAccount($propertyBag->getCustomProperty('account_holder'));
    $bankAccount->setBankName($propertyBag->getCustomProperty('bank_name'));
    $bankAccount->setAccountType('checking');
    return $bankAccount;
  }

  /**
   * Get the payment details for the subscription
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return AnetAPI\PaymentType
   */
  protected function getPaymentDetails(PropertyBag $propertyBag) {
    $bankAccount = $this->getBankAccount($propertyBag);
    // Add the payment data to a paymentType object
    $paymentDetails = new AnetAPI\PaymentType();
    $paymentDetails->setBankAccount($bankAccount);
    return $paymentDetails;
  }

}
