(function($, _, ts) {

  // Move the button to somewhere more visible
  if ($('.crm-localization-form-block').size() > 0) {
    // Localization Settings
    $('#uplang').prependTo($('.crm-localization-form-block'));
  }
  else if ($('.crm-content-block').size() > 0) {
    // Manage Extensions page
    $('#uplang').prependTo($('.crm-content-block'));
  }

  $('.crm-uplang-refresh').click(function(event) {
    event.stopPropagation();
    CRM.alert(ts('This can take a minute or two.'), ts('Refreshing...'), 'crm-msg-loading', {expires: 0});
    CRM.api('Uplang', 'fetch', {}, {
      'callBack' : function(result){
        if (result.is_error) {
          CRM.alert(result.error_message, ts('Refresh Error'), 'error');
        }
        else {
          CRM.closeAlertByChild($('.crm-msg-loading'));
          CRM.alert(ts('Updated %1 resources', {1: result.count}), ts('Done'), 'success');
        }
      }
    });
    return false;
  });

})(CRM.$, CRM._, CRM.ts('uplang'));
