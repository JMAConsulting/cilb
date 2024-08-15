jQuery(document).ready(function ($) {
  // Check if form is in English or Spanish
  var currentUrl = window.location.href;
  var lang;

  // Check if '/es/' is in the URL
  if (currentUrl.indexOf("/es/") !== -1) {
    lang = "es_MX";
  } else {
    lang = "en_US";
  }

  $('[data-drupal-selector="edit-proposed-exam-date"]').on(
    "change",
    function () {
      var selectedValue = $(this).val();

      console.log("Selected Value:", selectedValue);

      // Get event location
      CRM.api4("Event", "get", {
        select: ["address.*"],
        join: [
          [
            "Address AS address",
            "LEFT",
            ["loc_block_id.address_id", "=", "address.id"],
          ],
        ],
        where: [["id", "=", selectedValue]],
        language: lang,
      }).then(
        function (events) {
          if (events.length > 0) {
            var selectedEvent = events[0];
            console.log("Selected Event:", selectedEvent);

            // Check if address exists
            if (selectedEvent.address) {
              var fullAddress =
                selectedEvent.address.street_address +
                ", " +
                selectedEvent.address.city +
                ", " +
                selectedEvent["address.state_province_id:abbr"];

              // Set the full address in the input field
              $("#edit-proposed-location").val(fullAddress);
            } else {
              console.log("Address not found for the selected event.");
            }
          } else {
            console.log("No events found with the selected ID.");
          }
        },
        function (failure) {
          console.log("API Call Failed:", failure);
        }
      );
    }
  );
});
