jQuery(document).ready(function ($) {
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

  // TODO 1: show scope markup from description

  var examChoice = $(
    "#edit-select-exam-part"
  ).val();

  if ($("#edit-scope-markup").length) {
    $("#edit-scope-markup").empty();
    CRM.api4("Event", "get", {
      select: ["description", "title"],
      where: [["id", "=", examChoice]],
      language: lang,
      checkPermissions: false,
      limit: 1,
    }).then(function (events) {
      $("#edit-scope-markup").html(events[0]["description"]);
      $(".fieldset__legend .fieldset__label").append(
        " - " + events[0]["title"]
      );
    });
  }

  // TODO 2: check selection for any exam-specific prices
  // display and total them
  //
  // should be a lot simpler than the below?

  if ($("#edit-exam-fee-markup").length) {
    $("#edit-exam-fee-markup").empty();
    getPriceSet(examChoice);
  }

  function getPriceSet(eventId) {
    CRM.api4("PriceSetEntity", "get", {
      select: ["price_set_id"],
      where: [
        ["entity_table", "=", "civicrm_event"],
        ["entity_id", "=", eventId],
      ],
      language: lang,
    }).then(
      function (priceSets) {
        var priceSet = priceSets[0];

        var priceSetLabel;

        CRM.api4("PriceField", "get", {
          where: [["price_set_id", "=", priceSet["price_set_id"]]],
          language: lang,
        }).then(
          function (priceFields) {
            htmlType = priceFields[0]["html_type"];
            priceSetLabel = priceFields[0]["label"];

            CRM.api4("PriceFieldValue", "get", {
              where: [["price_field_id", "=", priceFields[0]["id"]]],
              language: lang,
            }).then(function (priceFieldValues) {
              if (priceFieldValues.length > 0) {
                var radioButtonsHtml = "";
                var priceSetHtml = "";
                var optionsHtml = "";
                var checkboxesHtml = "";

                // Add html options to the form depending on html type set in Civi

                priceFieldValues.forEach(function (item, index) {
                  var label = item["label"] + " - $" + item["amount"];
                  var amount = item["amount"];
                  var priceFieldValueId = item["id"];

                  if (htmlType == "Radio") {
                    radioButtonsHtml += `
                      <div class="form-type-boolean js-form-item form-item js-form-type-radio form-item-select-your-exam-category js-form-item-select-your-exam-category">
                          <input data-civicrm-field-key="exam_fee_front_end"
                                data-drupal-selector="edit-select-your-exam-category-${index}"
                                type="radio"
                                id="edit-select-your-exam-category-${index}"
                                name="exam_fee_front_end"
                                value="${item.amount}"
                                class="form-radio form-boolean form-boolean--type-radio exam-fee-radio"
                                data-class="${priceFieldValueId}"
                                required="required">
                          <label for="edit-select-your-exam-category-${index}" class="form-item__label option">${item.label} - $${item.amount}</label>
                      </div>`;
                  } else if (htmlType == "CheckBox") {
                    checkboxesHtml += `
                      <div class="form-type-boolean js-form-item form-item js-form-type-checkbox form-item-exam-fee-front-end-${index} js-form-item-exam-fee-front-end-${index}">
                          <input class="civicrm-enabled form-checkbox form-boolean form-boolean--type-checkbox exam-fee-checkbox"
                                  data-civicrm-field-key="exam_fee_front_end"
                                  data-drupal-selector="edit-exam-fee-front-end-${index}"
                                  type="checkbox"
                                  id="edit-exam-fee-front-end-${index}"
                                  name="exam_fee_front_end[${index}]"
                                  value="${amount}"
                                  data-class="${priceFieldValueId}">
                          <label for="edit-exam-fee-front-end-${index}" class="form-item__label option">${label}</label>
                      </div>`;
                  } else if (htmlType == "Select") {
                    optionsHtml += `<option value="${amount}" data-class="${priceFieldValueId}">${label}</option>`;
                  }
                });

                if (htmlType == "Radio") {
                  priceSetHtml = `
                    <div id="edit-select-your-exam-category" class="js-webform-webform-entity-radios webform-options-display-one-column form-boolean-group">
                        <label class="form-item__label">${priceSetLabel}</label>
                        ${radioButtonsHtml}
                    </div>`;
                } else if (htmlType === "CheckBox") {
                  priceSetHtml = `
                    <div class="form-type-checkboxes js-form-item form-item js-form-type-checkboxes form-item-exam-fee-front-end">
                        <label class="form-item__label">${priceSetLabel}</label>
                        ${checkboxesHtml}
                    </div>`;
                } else if (htmlType === "Select") {
                  // Add the default "Select" option here
                  optionsHtml =
                    `<option value="" disabled selected>- Select -</option>` +
                    optionsHtml;
                  priceSetHtml = `
                    <div class="form-type-select js-form-item form-item js-form-type-select form-item-exam-fee-front-end">
                        <label class="form-item__label">${priceSetLabel}</label>
                        <select class="civicrm-enabled form-select exam-fee-select"
                                data-civicrm-field-key="exam_fee_front_end"
                                data-drupal-selector="edit-exam-fee-front-end"
                                id="edit-exam-fee-front-end"
                                name="exam_fee_front_end">
                            ${optionsHtml}
                        </select>
                    </div>`;
                }

                $("#edit-exam-fee-markup").append(priceSetHtml);
                initializeSelectedFields();
              } else {
                console.log("No data found in the response");
              }
            });
          },
          function (failure) {
            console.log(failure);
          }
        );
      },
      function (failure) {
        // Handle failure
        console.log("Failed to fetch data: ", failure);
      }
    );
  }

  function updateFeeAmount(htmlType) {
    var total = 0;
    var selectedIds = [];

    if (htmlType === "CheckBox") {
      // Calculate the total from all checked checkboxes
      $("#edit-exam-fee-markup .exam-fee-checkbox:checked").each(function () {
        total += parseFloat($(this).val());
        selectedIds.push($(this).data("class"));
      });
    } else if (htmlType === "Radio") {
      // Get the value of the selected radio button
      var selectedRadio = $("#edit-exam-fee-markup .exam-fee-radio:checked");
      if (selectedRadio.length) {
        total = parseFloat(selectedRadio.val());
        selectedIds.push(selectedRadio.data("class"));
      }
    } else if (htmlType === "Select") {
      // Get the value of the selected option from the dropdown
      var selectedOption = $(
        "#edit-exam-fee-markup .exam-fee-select option:selected"
      );
      if (selectedOption.val()) {
        total = parseFloat(selectedOption.val());
        selectedIds.push(selectedOption.data("class"));
      }
    }

    // Update the fee amount input field
    $("#edit-civicrm-1-participant-1-participant-fee-amount").val(total);

    // Update the selected price field values input field with the array of selected IDs
    $("#edit-selected-pricefieldvalues").val(selectedIds.join(","));
  }

  // Add event listener to checkboxes (only once, after they've all been added)
  $("#edit-exam-fee-markup").on(
    "change",
    ".exam-fee-checkbox, .exam-fee-radio, .exam-fee-select",
    function () {
      updateFeeAmount(htmlType);
    }
  );

  function initializeSelectedFields() {
    if ($("#edit-selected-pricefieldvalues").length) {
      var selectedIds = $("#edit-selected-pricefieldvalues").val().split(",");

      // Check checkboxes based on the selected IDs
      $("#edit-exam-fee-markup .exam-fee-checkbox").each(function () {
        var dataClass = $(this).data("class");
        if (selectedIds.includes(dataClass.toString())) {
          $(this).prop("checked", true);
        }
      });

      // Check radio buttons based on the selected IDs
      $("#edit-exam-fee-markup .exam-fee-radio").each(function () {
        var dataClass = $(this).data("class");
        if (selectedIds.includes(dataClass.toString())) {
          $(this).prop("checked", true);
        }
      });

      // Select options in the dropdown based on the selected IDs
      $("#edit-exam-fee-markup .exam-fee-select option").each(function () {
        var dataClass = $(this).data("class");
        if (dataClass && selectedIds.includes(dataClass.toString())) {
          $(this).prop("selected", true);
        }
      });

      $(
        "#edit-exam-fee-markup .exam-fee-checkbox:checked, #edit-exam-fee-markup .exam-fee-radio:checked, #edit-exam-fee-markup .exam-fe-select"
      ).trigger("change");
    }
  }
});
