jQuery(document).ready(function($) {
  // Check if form is in English or Spanish
  var currentUrl = window.location.href;
  var lang;
  var htmlType;

  // Check if '/es/' is in the URL
  if (currentUrl.indexOf("/es/") !== -1) {
    lang = "es_MX";
  } else {
    lang = "en_US";
  }

  /**
   * Gets the address of a single event and fills the information in all html elements with id location_detail
   * @param eventID the id of the event
   */
  function fetchLocationDetail(eventID) {
    CRM.api4('Event', 'get', {
      select: ["address.street_address", "address.supplemental_address_1", "address.supplemental_address_2", "address.city", "address.state_province_id:abbr", "address.country_id:label", "address.postal_code"],
      join: [["Address AS address", "LEFT", ["loc_block_id.address_id", "=", "address.id"]]],
      where: [["id", "IN", eventID], ["Exam_Details.Exam_Part", "<>", "BF"]],
    }).then(function(events) {
      if (events.length > 0) {
        $('#edit-location-detail').show();

        var supplemental_address_2 = events[0]["address.supplemental_address_2"] == null ? '' : events[0]["address.supplemental_address_2"] + '<br/>',
          postal_code = events[0]["address.postal_code"] == null ? '' : events[0]["address.postal_code"] + '<br/>',
          street_address = events[0]["address.street_address"] == null ? '' : events[0]["address.street_address"] + '<br/>',
          supplemental_address_1 = events[0]["address.supplemental_address_1"] == null ? '' : events[0]["address.supplemental_address_1"] + '<br/>',
          state_province_id = events[0]["address.state_province_id:abbr"] == null ? '' : events[0]["address.state_province_id:abbr"] + ",",
          country_id = events[0]["address.country_id:label"] == null ? '' : events[0]["address.country_id:label"] + "<br/>";

        var address = street_address
          + supplemental_address_1
          + supplemental_address_2
          + postal_code
          + state_province_id + country_id + "<br/>";

        $('#location_detail').html(address);
      }
      else {
        $('#edit-location-detail').hide();
      }
    }, function(failure) {
      // handle failure
    });
  }

  const contributionAmountField = $("#edit-civicrm-1-contribution-1-contribution-total-amount");

  // show scope markup from event description
  const examCatIdField = $('[data-drupal-selector="edit-exam-category-id"]');
  const selectedCatId = examCatIdField.val();
  const eventIdsField = $('[data-drupal-selector="edit-event-ids"]');
  var selectedEventIds = eventIdsField.val();
  const scopeMarkup = $("#edit-scope-markup");

  if ($('#edit-exam-preference').length > 0 && $('#edit-exam-preference').val()) {
    fetchLocationDetail($('#edit-exam-preference').val());
  }
  else if ($('#edit-exam-preference').length > 0) {
    $('#edit-location-detail').hide();
  }

  $('#edit-exam-preference').on('change', function(e) { fetchLocationDetail($(this).val()); });


  // Fill the description of the Exam Category
  if (scopeMarkup.length) {
    scopeMarkup.empty();

    scopeMarkup.html('<div class="loader" />');

    CRM.api4("OptionValue", "get", {
      select: ["label", "description"],
      where: [["id", "=", selectedCatId]],
      language: lang,
      checkPermissions: false,
      limit: 1,
    }).then((categories) => {
      scopeMarkup.html(categories[0]["description"] ? categories[0]["description"] : "[Exam category description missing]");

      $(".fieldset__legend .fieldset__label").append(
        " - " + categories[0]["label"]
      );
    });
  }
});
