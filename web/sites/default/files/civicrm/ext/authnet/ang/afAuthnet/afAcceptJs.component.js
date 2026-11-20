(function (angular, $, _) {

  angular.module('afAuthnet').component('afAcceptJs', {
    require: {
      afCheckoutBlock: '^^afCheckoutBlock',
      afForm: '^^afForm',
    },
    // no content required
    template: '',

    controller: function ($scope, $element, $window, $q) {
      const ts = $scope.ts = CRM.ts('paypal');

      this.$onInit = () => {
        // register response handler in the global scope (see data-responsehandler attribute
        // of acceptjs button')
        window.afAcceptJsResponseHandler = (response) => this.onAcceptJsResponse(response);

        // we need a watch to wait until the default afform submit button is
        // rendered so we can replace it with our own
        $scope.$watch(() => this.getAfformSubmitButton(), (newVal) => {
          // no afform submit button yet
          if (!newVal) {
            return;
          }
          // button already set up, dont run again
          if (this.customSubmitButton) {
            return;
          }
          this.load();
        });
      };

      this.load = () => {
        this.loadButtons();
        return this.loadScript()
          .then(() => this.customSubmitButton.disabled = false)
          .catch(() => CRM.alert(ts('Sorry, there was an error loading Accept.js'), null,'error'));
      };

      this.$onDestroy = () => {
        // destroy our custom submit button
        this.customSubmitButton.remove();
        // unhide the usual submit button
        this.getAfformSubmitButton().style.display = null;
      };

      this.getAfformSubmitButton = () => $element[0].closest('af-form').querySelector('button[ng-click="afform.submit()"]');

      this.onAcceptJsResponse = (response) => {
        if (response.messages.resultCode === "Error") {
          const errorMessageUser = response.messages.message.map((message) => message.text).join(', ');
          CRM.alert(errorMessageUser, '', 'error');
        }
        else {
          // add tokens from Authorize to the payment_params that get submitted with Afform.submit
          this.afCheckoutBlock.checkout_params.dataDescriptor = response.opaqueData.dataDescriptor;
          this.afCheckoutBlock.checkout_params.dataValue = response.opaqueData.dataValue;
          this.afCheckoutBlock.exportParamValues();

          // now we have the tokens update the button text
          // NOTE: the button is hidden by default - but we
          // could expose it
          this.acceptUiButton.innerText = ts('Re-enter card details');

          this.afForm.submit();
        }
      };

      this.loadButtons = () => {
        // define the JS response handler in the global scope
        // get the default submit button (we'll grab some things from it)
        const afformSubmitButton = this.getAfformSubmitButton();

        const {apiLoginId, clientKey} = this.afCheckoutBlock.getCheckoutOption();

        this.acceptUiButton = document.createElement('button');

        this.acceptUiButton.type = 'button';
        this.acceptUiButton.classList.add('AcceptUI');

        this.acceptUiButton.dataset.billingaddressoptions = '{"show":true, "required":false}';
        this.acceptUiButton.dataset.apiloginid = apiLoginId;
        this.acceptUiButton.dataset.clientkey = clientKey;
        this.acceptUiButton.dataset.acceptuiformbtntxt = afformSubmitButton.innerText;
        this.acceptUiButton.dataset.acceptuiformHeadertxt = ts('Card Information');
        this.acceptUiButton.dataset.paymentoptions = '{"showCreditCard": true, "showBankAccount": true}';
        this.acceptUiButton.dataset.responsehandler = 'afAcceptJsResponseHandler';

        // TODO: maybe the form builder wants to expose the "Add card details" button
        // separately to the submit button. We could show "Card details added" when
        // the user has gone through the AcceptJS popup and we have got the payment
        // intent details
        this.acceptUiButton.style.display = 'none';
        this.acceptUiButton.innerText = ts('Add card details');
        $element[0].append(this.acceptUiButton);

        // custom submit button
        this.customSubmitButton = document.createElement('button');
        this.customSubmitButton.type = 'button';

        // copy button content from whatever is configured on the form
        this.customSubmitButton.innerHTML = afformSubmitButton.innerHTML;
        this.customSubmitButton.onclick = () => this.onButtonClick();
        this.customSubmitButton.disabled = true;

        // swap the buttons
        afformSubmitButton.style.display = 'none';
        afformSubmitButton.after(this.customSubmitButton);
      };

      this.loadScript = () => $q((resolve, reject) => {
        const {clientScript} = this.afCheckoutBlock.getCheckoutOption();

        const scriptTag = document.createElement('script');
        scriptTag.setAttribute('src', clientScript);

        scriptTag.addEventListener('load', resolve);
        scriptTag.addEventListener('error', reject);

        $element[0].append(scriptTag);
      });

      this.onButtonClick = (event) => {
        // TODO: would prefer to use an afForm.validate method
        // that could more consistently validate and/or show warnings
        if (!this.afForm.ngForm.$valid) {
          CRM.alert(ts('Please complete all required fields'), '', 'warning');
          return;
        }
        // click the AcceptUI button to load the AcceptUI popup
        this.acceptUiButton.click();
      };

    }
  });
}) (angular, CRM.$, CRM._);