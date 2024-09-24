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

class CRM_Core_Payment_AuthNetAcceptjs extends CRM_Core_Payment_AuthorizeNetCommon {

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
    return E::ts('Authorize.net (Accept.js)');
  }

  /**
   * Override CRM_Core_Payment function
   *
   * @return array
   */
  public function getPaymentFormFields() {
    return [];
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
    return [];
  }

  /**
   * @param \CRM_Core_Form $form
   *
   * @return bool|void
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function buildForm(&$form) {
    \Civi::resources()->addMarkup('
    <div id="anetacceptjs">
    <button type="button"
        class="AcceptUI"
        data-billingAddressOptions=\'{"show":true, "required":false}\'
        data-apiLoginID="' . self::getApiLoginId($this->_paymentProcessor) . '"
        data-clientKey="' . self::getSignature($this->_paymentProcessor) . '"
        data-acceptUIFormBtnTxt="Submit"
        data-acceptUIFormHeaderTxt="Card Information"
        data-paymentOptions=\'{"showCreditCard": true, "showBankAccount": true}\'
        data-responseHandler="authnetHandleResponse"
        style="display: none"
        >
        Pay
    </button>
    </div>

<script type="text/javascript">
  function authnetHandleResponse(response) {
    CRM.payment.authnetecheck.responseHandler(response);
  }
</script>
      ',
      ['region' => 'billing-block']
    );
    $anetJS = 'https://js.authorize.net/v3/AcceptUI.js';
    if ($form->_paymentProcessor['is_test']) {
      $anetJS = 'https://jstest.authorize.net/v3/AcceptUI.js';
    }
    CRM_Core_Region::instance('billing-block')->addScriptUrl($anetJS);

    CRM_Core_Region::instance('billing-block')->add([
      'scriptUrl' => \Civi::service('asset_builder')->getUrl(
        'civicrmAuthNet.js',
        [
          'path' => \Civi::resources()->getPath(E::LONG_NAME, 'js/civicrmAuthNetAccept.js'),
          'mimetype' => 'application/javascript',
        ]
      ),
      // Load after other scripts on form (default = 1)
      'weight' => 100,
    ]);

    $jsVars = [
      'id' => $form->_paymentProcessor['id'],
    ];
    \Civi::resources()->addVars(E::SHORT_NAME, $jsVars);

    // Enable JS validation for forms so we only (submit) create a paymentIntent when the form has all fields validated.
    $form->assign('isJsValidate', TRUE);
  }


  /**
   * Function to action pre-approval if supported
   *
   * @param array $params
   *   Parameters from the form
   *
   * This function returns an array which should contain
   *   - pre_approval_parameters (this will be stored on the calling form & available later)
   *   - redirect_url (if set the browser will be redirected to this.
   *
   * @return array
   */
  public function doPreApproval(&$params) {
    $preApprovalParams['dataDescriptor'] = CRM_Utils_Request::retrieveValue('dataDescriptor', 'String');
    $preApprovalParams['dataValue'] = CRM_Utils_Request::retrieveValue('dataValue', 'String');
    return ['pre_approval_parameters' => $preApprovalParams];
  }

  /**
   * Get any details that may be available to the payment processor due to an approval process having happened.
   *
   * In some cases the browser is redirected to enter details on a processor site. Some details may be available as a
   * result.
   *
   * @param array $storedDetails
   *
   * @return array
   */
  public function getPreApprovalDetails($storedDetails) {
    return $storedDetails ?? [];
  }

  /**
   * @param \Civi\Payment\PropertyBag $propertyBag
   *
   * @return \net\authorize\api\contract\v1\PaymentType|void
   */
  protected function getPaymentDetails(PropertyBag $propertyBag) {
    $propertyBag = $this->getTokenParameter('dataDescriptor', $propertyBag, TRUE);
    $propertyBag = $this->getTokenParameter('dataValue', $propertyBag, TRUE);
    // Create the payment object for a payment nonce
    $opaqueData = new AnetAPI\OpaqueDataType();
    $opaqueData->setDataDescriptor($propertyBag->getCustomProperty('dataDescriptor'));
    $opaqueData->setDataValue($propertyBag->getCustomProperty('dataValue'));

    // Add the payment data to a paymentType object
    $paymentOne = new AnetAPI\PaymentType();
    $paymentOne->setOpaqueData($opaqueData);
    return $paymentOne;
  }

  /**
   * Is an authorize-capture flow supported.
   *
   * @return bool
   */
  protected function supportsPreApproval() {
    return TRUE;
  }

}
