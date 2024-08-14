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

  $("#edit-proposed-exam-date").on("change", function () {
    var selectedValue = $(this).val();

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
        selectedEvent = events[0];
        $("#edit-proposed-location").val(
          selectedEvent.address.street_address +
            ", " +
            selectedEvent.address.city +
            ", " +
            selectedEvent["address.state_province_id:abbr"]
        );
      },
      function (failure) {
        console.log(failure);
      }
    );
  });
});
