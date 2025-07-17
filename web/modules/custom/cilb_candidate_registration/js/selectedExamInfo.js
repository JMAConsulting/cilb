jQuery(document).ready(function ($) {
  const $eventIdField = $('#edit-exam-preference');
  const $locationDetail = $('#edit-location-detail');

  /**
   * Gets the address of a single event and fills the information in all html elements with id location_detail
   * @param eventID the id of the event
   */
  const updateLocationDetail = (eventID) => {
    if (!eventID) {
      $locationDetail.hide();
    }

    CRM.api4('Event', 'get', {
      select: ["address.street_address", "address.supplemental_address_1", "address.supplemental_address_2", "address.city", "address.state_province_id:abbr", "address.country_id:label", "address.postal_code"],
      join: [["Address AS address", "LEFT", ["loc_block_id.address_id", "=", "address.id"]]],
      where: [["id", "IN", eventID], ["Exam_Details.Exam_Part", "<>", "BF"]],
    }).then((events) => {
      if (!events.length) {
        $locationDetail.hide();
        return;
      }

      $locationDetail.show();

      const event = events[0];

      const supplemental_address_2 = event["address.supplemental_address_2"] == null ? '' : event["address.supplemental_address_2"] + '<br/>',
            postal_code = event["address.postal_code"] == null ? '' : event["address.postal_code"] + '<br/>',
            street_address = event["address.street_address"] == null ? '' : event["address.street_address"] + '<br/>',
            supplemental_address_1 = event["address.supplemental_address_1"] == null ? '' : event["address.supplemental_address_1"] + '<br/>',
            state_province_id = event["address.state_province_id:abbr"] == null ? '' : event["address.state_province_id:abbr"] + ",",
            country_id = event["address.country_id:label"]  == null ? '' : event["address.country_id:label"] + "<br/>";

      const address = street_address
         + supplemental_address_1
         + supplemental_address_2
         + postal_code
         + state_province_id  + country_id + "<br/>";

      $('#location_detail').html(address);

    })
    .catch((failure) => {
      // handle failure
    });
  }

  if ($eventIdField.length > 0) {
    updateLocationDetail($eventIdField.val());

    $eventIdField.on('change', () => updateLocationDetail($eventIdField.val()));
  }

  // hide contribution amount field
  const $contributionAmountField = $("#edit-civicrm-1-contribution-1-contribution-total-amount");
  $contributionAmountField.parent().hide();

});
