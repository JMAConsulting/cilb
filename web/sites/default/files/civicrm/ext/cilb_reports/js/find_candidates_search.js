(function($, CRM) {
  function waitForElm(selector) {
    return new Promise(resolve => {
      if (document.querySelector(selector)) {
        return resolve(document.querySelector(selector));
      }

      const observer = new MutationObserver(mutations => {
        if (document.querySelector(selector)) {
          observer.disconnect();
          resolve(document.querySelector(selector));
        }
      });

      // If you get "parameter 1 is not of type 'Node'" error, see https://stackoverflow.com/a/77855838/492336
      observer.observe(document.documentElement, {
        childList: true,
        subtree: true
      });
    });
  }
  waitForElm("[id^='event-id']").then(function() {
    const searchParams = new URLSearchParams(window.location.search);
    for (const [key, value] of searchParams) {
      if (key == 'event' && !isNaN(parseFloat(value))) {
        $("[id^='event-id']").val(value).trigger('change');
      }
      if (key == 'status') {
        const separator = $("[id^='status-id']").attr('ng-list');
        if (value == 'true') {
          $("[id^='status-id']").val(CRM.vars.eventSearch.positiveStatus.join(separator)).trigger('change');
        }
        else {
          $("[id^='status-id']").val(CRM.vars.eventSearch.negativeStatus.join(separator)).trigger('change');
        }
      }
    }
  });
}(CRM.$, CRM));
