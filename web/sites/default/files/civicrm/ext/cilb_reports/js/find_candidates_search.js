(function($, CRM) {
  $(document).on('DomContentLoaded', function() {
    const searchParams = new URLSearchParams(window.location.search);
    for (const [key, value] of searchParams) {
      if (key == 'event' && !isNaN(parseFloat(value))) {
        $("[id^='event-id']").val(value).trigger('change');
      }
      if (key == 'status') {
        const separator = $("[id^='status-id']").attr('ng-list');
        if (value) {
          $("[id^='status-id']").val(CRM.vars.eventSearch.positiveStatus.join(separator)).trigger('change');
        }
        else {
          $("[id^='status-id']").val(CRM.vars.eventSearch.negativeStatus.join(separator)).trigger('change');
        }
      }
    }
  });
}(CRM.$, CRM));