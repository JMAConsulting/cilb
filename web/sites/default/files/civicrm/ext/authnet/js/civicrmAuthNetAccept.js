/**
 * JS Integration between CiviCRM & AuthorizeNet using Accept.js.
 */
(function($, ts) {
  var script = {
    name: 'authnetecheck',

    /**
     * Output debug information
     * @param {string} errorCode
     */
    debugging: function(errorCode) {
      CRM.payment.debugging(script.name, errorCode);
    },

    /**
     * Destroy any payment elements we have already created
     */
    destroyPaymentElements: function() {},

    /**
     * Payment processor is not ours - cleanup
     */
    notScriptProcessor: function() {
      script.debugging('New payment processor is not ' + script.name + ', clearing CRM.vars.' + script.name);
      script.destroyPaymentElements();
      delete (CRM.vars[script.name]);
      $(CRM.payment.getBillingSubmit()).show();
    },


    /**
     * This is called once Stripe elements have finished loading onto the form
     */
    doAfterElementsHaveLoaded: function() {
      CRM.payment.setBillingFieldsRequiredForJQueryValidate();

      // If another submit button on the form is pressed (eg. apply discount)
      //  add a flag that we can set to stop payment submission
      CRM.payment.form.dataset.submitdontprocess = 'false';

      CRM.payment.addHandlerNonPaymentSubmitButtons();

      for (i = 0; i < CRM.payment.submitButtons.length; ++i) {
        CRM.payment.submitButtons[i].addEventListener('click', submitButtonClick);
      }

      function submitButtonClick(clickEvent) {
        // Take over the click function of the form.
        if (typeof CRM.vars[script.name] === 'undefined') {
          // Do nothing. Not our payment processor
          return false;
        }
        script.debugging('clearing submitdontprocess');
        CRM.payment.form.dataset.submitdontprocess = 'false';

        // Run through our own submit
        return script.submit(clickEvent);
      }

      // Remove the onclick attribute added by CiviCRM.
      for (i = 0; i < CRM.payment.submitButtons.length; ++i) {
        CRM.payment.submitButtons[i].removeAttribute('onclick');
      }

      CRM.payment.addSupportForCiviDiscount();

      // For CiviCRM Webforms.
      if (CRM.payment.getIsDrupalWebform()) {
        // We need the action field for back/submit to work and redirect properly after submission

        $('[type=submit]').click(function() {
          CRM.payment.addDrupalWebformActionElement(this.value);
        });
        // If enter pressed, use our submit function
        CRM.payment.form.addEventListener('keydown', function (keydownEvent) {
          if (keydownEvent.code === 'Enter') {
            CRM.payment.addDrupalWebformActionElement(this.value);
            script.submit(keydownEvent);
          }
        });

        $('#billingcheckbox:input').hide();
        $('label[for="billingcheckbox"]').hide();
      }

      CRM.payment.triggerEvent('crmBillingFormReloadComplete', script.name);
      CRM.payment.triggerEvent('crmAuthnetFormReloadComplete', script.name);
    },

    submit: function(submitEvent) {
      submitEvent.preventDefault();
      script.debugging('submit handler');

      if (CRM.payment.form.dataset.submitted === 'true') {
        return;
      }
      CRM.payment.form.dataset.submitted = 'true';

      if (!CRM.payment.validateCiviDiscount()) {
        return false;
      }

      if (!CRM.payment.validateForm()) {
        return false;
      }

      if (!CRM.payment.validateReCaptcha()) {
        return false;
      }

      if (typeof CRM.vars[script.name] === 'undefined') {
        script.debugging('Submitting - not a ' + script.name + ' processor');
        return true;
      }

      var scriptProcessorId = parseInt(CRM.vars[script.name].id);
      var chosenProcessorId = null;

      // Handle multiple payment options and Stripe not being chosen.
      if (CRM.payment.getIsDrupalWebform()) {
        // this element may or may not exist on the webform, but we are dealing with a single (stripe) processor enabled.
        if (!$('input[name="submitted[civicrm_1_contribution_1_contribution_payment_processor_id]"]').length) {
          chosenProcessorId = scriptProcessorId;
        }
        else {
          chosenProcessorId = parseInt(CRM.payment.form.querySelector('input[name="submitted[civicrm_1_contribution_1_contribution_payment_processor_id]"]:checked').value);
        }
      }
      else {
        // Most forms have payment_processor-section but event registration has credit_card_info-section
        if ((CRM.payment.form.querySelector(".crm-section.payment_processor-section") !== null) ||
          (CRM.payment.form.querySelector(".crm-section.credit_card_info-section") !== null)) {
          scriptProcessorId = CRM.vars[script.name].id;
          if (CRM.payment.form.querySelector('input[name="payment_processor_id"]:checked') !== null) {
            chosenProcessorId = parseInt(CRM.payment.form.querySelector('input[name="payment_processor_id"]:checked').value);
          }
        }
      }

      // If any of these are true, we are not using the stripe processor:
      // - Is the selected processor ID pay later (0)
      // - Is the Stripe processor ID defined?
      // - Is selected processor ID and stripe ID undefined? If we only have stripe ID, then there is only one (stripe) processor on the page
      if ((chosenProcessorId === 0) || (scriptProcessorId === null) ||
        ((chosenProcessorId === null) && (scriptProcessorId === null))) {
        script.debugging('Not a ' + script.name + ' transaction, or pay-later');
        return CRM.payment.doStandardFormSubmit();
      }
      else {
        script.debugging(script.name + ' is the selected payprocessor');
      }

      // Don't handle submits generated by the CiviDiscount button etc.
      if (CRM.payment.form.dataset.submitdontprocess === 'true') {
        script.debugging('non-payment submit detected - not submitting payment');
        return true;
      }

      if (CRM.payment.getIsDrupalWebform()) {
        // If we have selected Stripe but amount is 0 we don't submit via Stripe
        if ($('#billing-payment-block').is(':hidden')) {
          script.debugging('no payment processor on webform');
          return true;
        }

        // If we have more than one processor (user-select) then we have a set of radio buttons:
        var $processorFields = $('[name="submitted[civicrm_1_contribution_1_contribution_payment_processor_id]"]');
        if ($processorFields.length) {
          if ($processorFields.filter(':checked').val() === '0' || $processorFields.filter(':checked').val() === 0) {
            script.debugging('no payment processor selected');
            return true;
          }
        }
      }

      var totalAmount = CRM.payment.getTotalAmount();
      if (totalAmount === 0.0) {
        script.debugging("Total amount is 0");
        return CRM.payment.doStandardFormSubmit();
      }

      // Accept.js pops up a "modal" lightbox so we don't need to disable submit button
      // Set flag back to false so we can re-submit if we cancel payment.
      CRM.payment.form.dataset.submitted = 'false';
      CRM.payment.getBillingForm().querySelector('button.AcceptUI').click();

      // Actual submit is handled by Accept.js lightbox.
      // If that is cancelled we don't want to submit form.
      return false;
    },

    responseHandler: function(response) {
      script.debugging('responseHandler');
      if (response.messages.resultCode === "Error") {
        var i = 0;
        var errorMessage = '';
        var errorMessageUser = '';
        while (i < response.messages.message.length) {
          errorMessage = errorMessage +
            response.messages.message[i].code + ": " +
            response.messages.message[i].text;
          errorMessageUser = errorMessageUser + response.messages.message[i].text;
          i = i + 1;
        }
        script.debugging(errorMessage);
        CRM.payment.swalFire({
          icon: 'error',
          text: '',
          title: errorMessageUser
        }, '#card-element', true);
        CRM.payment.triggerEvent('crmBillingFormNotValid');
      }
      else {
        this.paymentFormUpdate(response.opaqueData);
      }
    },

    paymentFormUpdate: function(opaqueData) {
      script.debugging('paymentFormUpdate');
      // Insert the token ID into the form so it gets submitted to the server
      var hiddenDataDescriptor = document.createElement('input');
      hiddenDataDescriptor.setAttribute('type', 'hidden');
      hiddenDataDescriptor.setAttribute('name', 'dataDescriptor');
      hiddenDataDescriptor.setAttribute('value', opaqueData.dataDescriptor);
      CRM.payment.form.appendChild(hiddenDataDescriptor);
      var hiddenDataValue = document.createElement('input');
      hiddenDataValue.setAttribute('type', 'hidden');
      hiddenDataValue.setAttribute('name', 'dataValue');
      hiddenDataValue.setAttribute('value', opaqueData.dataValue);
      CRM.payment.form.appendChild(hiddenDataValue);

      CRM.payment.getBillingForm().submit();
    }
  };

  // Disable the browser "Leave Page Alert" which is triggered because we mess with the form submit function.
  window.onbeforeunload = null;

  if (CRM.payment.hasOwnProperty(script.name)) {
    return;
  }

  // Load the script into the CRM.payment object
  var crmPaymentObject = {};
  crmPaymentObject[script.name] = script;
  $.extend(CRM.payment, crmPaymentObject);

  CRM.payment.registerScript(script.name);

  // Re-prep form when we've loaded a new payproc via ajax or via webform
  $(document).ajaxComplete(function (event, xhr, settings) {
    if (CRM.payment.isAJAXPaymentForm(settings.url)) {
      CRM.payment.debugging(script.name, 'triggered via ajax');
      load();
    }
  });

  document.addEventListener('DOMContentLoaded', function() {
    CRM.payment.debugging(script.name, 'DOMContentLoaded');
    load();
  });

  /**
   * Called on every load of this script (whether billingblock loaded via AJAX or DOMContentLoaded)
   */
  function load() {
    if (window.civicrmAuthNetAcceptJsHandleReload) {
      // Call existing instance of this, instead of making new one.
      CRM.payment.debugging(script.name, "calling existing HandleReload.");
      window.civicrmAuthNetAcceptJsHandleReload();
    }
  }

  /**
   * This function boots the UI.
   */
  window.civicrmAuthNetAcceptJsHandleReload = function() {
    CRM.payment.scriptName = script.name;
    CRM.payment.debugging(script.name, 'HandleReload');

    // Get the form containing payment details
    CRM.payment.form = CRM.payment.getBillingForm();
    if (typeof CRM.payment.form.length === 'undefined' || CRM.payment.form.length === 0) {
      CRM.payment.debugging(script.name, 'No billing form!');
      return;
    }

    // If we are reloading start with the form submit buttons visible
    // They may get hidden later depending on the element type.
    $(CRM.payment.getBillingSubmit()).show();

    // Load Stripe onto the form.
    var anetForm = CRM.payment.getBillingForm().querySelector('#anetacceptjs');
    if (anetForm !== null) {
      CRM.payment.debugging(script.name, 'preparing form');
      //$(CRM.payment.submitButtons).hide();
      script.doAfterElementsHaveLoaded();
    }
    else {
      script.notScriptProcessor();
      CRM.payment.triggerEvent('crmBillingFormReloadComplete', script.name);
    }
  };

}(CRM.$, CRM.ts('com.donordepot.authnetecheck')));
