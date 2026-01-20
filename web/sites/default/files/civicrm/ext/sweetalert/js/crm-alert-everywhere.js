(function (CRM) {
  /**
   * Override the core CRM alert to use sweetalert everywhere
   */
  // backup the core function
  CRM._alert = CRM.alert;

  CRM.alert = function (text, title, type, options) {
    if (type === 'crm-help crm-msg-loading') {
      CRM._alert(text, title, type, options);
      return;
    }
    Swal.fire({
      icon: type,
      html: text || '',
      title: title || '',
      theme: CRM.vars.sweetalert.darkMode,
    });
  };
})(CRM);