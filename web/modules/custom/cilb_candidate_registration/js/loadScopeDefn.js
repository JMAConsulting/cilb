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

  var examChoice = $(
    "#edit-civicrm-1-participant-1-participant-event-id"
  ).val();

  if ($("#edit-civicrm-1-participant-1-participant-fee-amount").length) {
    getPriceSet(examChoice);
  }
  if ($("#edit-scope-markup").length) {
    CRM.api4("Event", "get", {
      select: ["description", "title"],
      where: [["id", "=", examChoice]],
      language: lang,
      checkPermissions: false,
      limit: 1,
    }).then(function (events) {
      console.log(events);
      $("#edit-scope-markup").html(events[0]["description"]);
      $(".fieldset__legend .fieldset__label").append(
        " - " + events[0]["title"]
      );
    });
  }

  function getPriceSet(eventId) {
    CRM.api4("PriceSetEntity", "get", {
      select: ["price_set_id"],
      where: [
        ["entity_table", "=", "civicrm_event"],
        ["entity_id", "=", eventId],
      ],
      language: lang,
      checkPermissions: false,
    }).then(
      function (priceSets) {
        // Extract the price_set_id values into an array
        var priceSetIds = priceSets.map(function (priceSet) {
          return priceSet.price_set_id;
        });

        CRM.api4("PriceFieldValue", "get", {
          where: [["price_field_id", "IN", priceSetIds]],
          language: lang,
          checkPermissions: false,
        }).then(function (priceFieldValues) {
          // Check if the priceSetEntities array contains data
          if (priceFieldValues.length > 0) {
            // Clear any existing checkboxes
            $("#edit-exam-fee-markup").empty();

            // Iterate through the priceSetEntities array and create new checkboxes
            priceFieldValues.forEach(function (item, index) {
              var label = item["label"] + " - $" + item["amount"];
              var amount = item["amount"];

              // Create a new checkbox input and label
              var checkboxHtml = `
                <div class="form-type-boolean js-form-item form-item js-form-type-checkbox form-item-exam-fee-front-end-${index} js-form-item-exam-fee-front-end-${index}">
                    <input class="civicrm-enabled form-checkbox form-boolean form-boolean--type-checkbox exam-fee-checkbox" 
                        data-civicrm-field-key="exam_fee_front_end" 
                        data-drupal-selector="edit-exam-fee-front-end-${index}" 
                        type="checkbox" 
                        id="edit-exam-fee-front-end-${index}" 
                        name="exam_fee_front_end[${index}]" 
                        value="${amount}">
                    <label for="edit-exam-fee-front-end-${index}" class="form-item__label option">${label}</label>
                </div>
                `;

              // Append the new checkbox to the new container
              $("#edit-exam-fee-markup").append(checkboxHtml);
            });

            // Add event listener to checkboxes (only once, after they've all been added)
            $("#edit-exam-fee-markup").on(
              "change",
              ".exam-fee-checkbox",
              function () {
                updateFeeAmount();
              }
            );
          } else {
            console.log("No data found in the response");
          }
        });
      },
      function (failure) {
        // Handle failure
        console.log("Failed to fetch data: ", failure);
      }
    );
  }

  // Update the fee amount input field based on checked checkboxes
  function updateFeeAmount() {
    var total = 0;
    $("#edit-exam-fee-markup .exam-fee-checkbox:checked").each(function () {
      total += parseFloat($(this).val());
    });
    $("#edit-civicrm-1-participant-1-participant-fee-amount").val(total);
  }
});
