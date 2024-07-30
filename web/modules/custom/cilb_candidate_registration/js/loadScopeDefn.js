jQuery(document).ready(function ($) {
  if ($("#edit-scope-markup").length) {
    var storedMarkup = localStorage.getItem("temp_markup");
    if (storedMarkup) {
      $("#edit-scope-markup").html(storedMarkup);
    }

    var examChoice = localStorage.getItem("exam_choice");

    if (examChoice) {
      $(".fieldset__legend .fieldset__label").append(" - " + examChoice);
    }
  }

  if ($("#edit-civicrm-1-participant-1-participant-fee-amount").length) {
    var examChoice = $(
      "#edit-civicrm-1-participant-1-participant-event-id"
    ).val();

    getPriceSet(examChoice);
  }

  function getPriceSet(eventId) {
    CRM.api4("PriceSetEntity", "get", {
      select: ["price_field_value.*"],
      join: [
        [
          "PriceFieldValue AS price_field_value",
          "INNER",
          ["price_set_id", "=", "price_field_value.price_field_id"],
        ],
      ],
      where: [
        ["entity_table", "=", "civicrm_event"],
        ["entity_id", "=", eventId],
      ],
    }).then(
      function (priceSetEntities) {
        // Check if the priceSetEntities array contains data
        if (priceSetEntities.length > 0) {
          // Clear any existing checkboxes
          $("#edit-exam-fee-markup").empty();

          // Iterate through the priceSetEntities array and create new checkboxes
          priceSetEntities.forEach(function (item, index) {
            var label =
              item["price_field_value.label"] +
              " - $" +
              item["price_field_value.amount"];
            var amount = item["price_field_value.amount"];

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
