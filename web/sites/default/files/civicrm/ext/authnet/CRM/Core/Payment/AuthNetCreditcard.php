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

use Civi\Payment\Exception\PaymentProcessorException;
use Civi\Payment\PropertyBag;
use CRM_AuthNetEcheck_ExtensionUtil as E;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use net\authorize\api\constants\ANetEnvironment as AnetEnvironment;

class CRM_Core_Payment_AuthNetCreditcard extends CRM_Core_Payment_AuthorizeNetCommon {

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
    return 'credit_card';
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return string
   */
  public function getPaymentTypeLabel() {
    return E::ts('Authorize.net (Credit Card)');
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return array
   */
  public function getPaymentFormFields() {
    return [
      'credit_card_type',
      'credit_card_number',
      'cvv2',
      'credit_card_exp_date',
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
    //@todo convert credit card type into an option value
    $creditCardType = ['' => E::ts('- select -')] + CRM_Contribute_PseudoConstant::creditCard();
    $isCVVRequired = Civi::settings()->get('cvv_backoffice_required');
    if (!$this->isBackOffice()) {
      $isCVVRequired = TRUE;
    }
    return [
      'credit_card_number' => [
        'htmlType' => 'text',
        'name' => 'credit_card_number',
        'title' => E::ts('Card Number'),
        'attributes' => [
          'size' => 20,
          'maxlength' => 20,
          'autocomplete' => 'off',
          'class' => 'creditcard',
        ],
        'is_required' => TRUE,
        // 'description' => '16 digit card number', // If you enable a description field it will be shown below the field on the form
      ],
      'cvv2' => [
        'htmlType' => 'text',
        'name' => 'cvv2',
        'title' => E::ts('Security Code'),
        'attributes' => [
          'size' => 5,
          'maxlength' => 10,
          'autocomplete' => 'off',
        ],
        'is_required' => $isCVVRequired,
        'rules' => [
          [
            'rule_message' => E::ts('Please enter a valid value for your card security code. This is usually the last 3-4 digits on the card\'s signature panel.'),
            'rule_name' => 'integer',
            'rule_parameters' => NULL,
          ],
        ],
      ],
      'credit_card_exp_date' => [
        'htmlType' => 'date',
        'name' => 'credit_card_exp_date',
        'title' => E::ts('Expiration Date'),
        'attributes' => CRM_Core_SelectValues::date('creditCard'),
        'is_required' => TRUE,
        'rules' => [
          [
            'rule_message' => E::ts('Card expiration date cannot be a past date.'),
            'rule_name' => 'currentDate',
            'rule_parameters' => TRUE,
          ],
        ],
        'extra' => ['class' => 'crm-form-select'],
      ],
      'credit_card_type' => [
        'htmlType' => 'select',
        'name' => 'credit_card_type',
        'title' => E::ts('Card Type'),
        'attributes' => $creditCardType,
        'is_required' => FALSE,
      ],
    ];
  }

  /**
   * Get the Credit card details for AuthNet
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \net\authorize\api\contract\v1\CreditCardType
   */
  private function getCreditCard(PropertyBag $propertyBag) {
    // Create the payment data for a CreditCard
    $creditCard = new AnetAPI\CreditCardType();
    $creditCard->setCardNumber($propertyBag->getCustomProperty('credit_card_number'));
    $creditCard->setExpirationDate($propertyBag->getCustomProperty('year') . '-' . $propertyBag->getCustomProperty('month'));
    if ($propertyBag->offsetExists('cvv2') || !$this->isBackOffice() || \Civi::settings()->get('cvv_backoffice_required')) {
      $creditCard->setCardCode($propertyBag->getCustomProperty('cvv2'));
    }
    return $creditCard;
  }

  /**
   * Get the payment details for the subscription
   *
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \net\authorize\api\contract\v1\PaymentType|void
   */
  protected function getPaymentDetails(PropertyBag $propertyBag) {
    $creditCard = $this->getCreditCard($propertyBag);
    // Add the payment data to a paymentType object
    $paymentDetails = new AnetAPI\PaymentType();
    $paymentDetails->setCreditCard($creditCard);
    return $paymentDetails;
  }

}
