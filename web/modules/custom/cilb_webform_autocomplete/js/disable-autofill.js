(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.cilbWebformDisableAutofill = {
    attach: function (context) {
      once('cilb-no-autofill-form', 'form.webform-submission-form', context).forEach(function (form) {
        form.setAttribute('autocomplete', 'off');
      });

      var selector = 'form.webform-submission-form input, form.webform-submission-form select, form.webform-submission-form textarea';

      once('cilb-no-autofill-field', selector, context).forEach(function (el) {
        var type = (el.getAttribute('type') || '').toLowerCase();

        if (type === 'hidden' || type === 'submit' || type === 'button') {
          return;
        }

        if (type === 'password') {
          el.setAttribute('autocomplete', 'new-password');
        }
        else {
          el.setAttribute('autocomplete', 'off');
        }
      });
    }
  };
})(Drupal, once);
