(function (CRM) {
  /**
   * Override the core CRM alert to use sweetalert if the notification
   * container is not present
   */

  // backup the core function
  CRM._alert = CRM.alert;

  CRM.alert = function (text, title, type, options) {
    if (CRM.$('#crm-notification-container').length) {
      CRM._alert(text, title, type, options);
    }
    else {
      Swal.fire({
        icon: type,
        html: text || '',
        title: title || '',
        theme: CRM.vars.sweetalert.darkMode,
      });
    }
  };
})(CRM);