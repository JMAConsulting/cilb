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

  const $contributionAmountField = $("#edit-civicrm-1-contribution-1-contribution-total-amount");
  $contributionAmountField.parent().hide();

    // show scope markup from event description
  const $examCatIdField = $('[data-drupal-selector="edit-exam-category-id"]');
  const selectedCatId = $examCatIdField.val();
  const $eventIdsField = $('[data-drupal-selector="edit-event-ids"]');
  const selectedEventIds = $eventIdsField.val();

  const $scopeMarkup = $("#edit-scope-markup");


  if ($scopeMarkup.length) {
    $scopeMarkup.empty();

    CRM.api4("OptionValue", "get", {
      select: ["label", "description"],
      where: [["id", "=", selectedCatId]],
      language: lang,
      checkPermissions: false,
      limit: 1,
    }).then((categories) => {
      $scopeMarkup.html(categories[0]["description"]);
      $(".fieldset__legend .fieldset__label").append(
        " - " + categories[0]["label"]
      );
    });
  }

  // check selection for any exam-specific prices
  // display and total them
  const $examFeeMarkup = $("#edit-exam-fee-markup");
  if ($examFeeMarkup.length) {

    $examFeeMarkup.empty();

    const lineItems = [];

    CRM.api4("Event", "get", {
      select: ["title", "Exam_Details.Exam_Format"],
      where: [["id", "IN", selectedEventIds]],
      language: lang,
      checkPermissions: false,
    }).then((events) => Promise.all(events.map((event) =>
        // for each event we just fetch the first price set
        // and the first price set value
        CRM.api4("PriceSetEntity", "get", {
          select: ["price_set_id"],
          where: [
            ["entity_table", "=", "civicrm_event"],
            ["entity_id", "=", event.id],
          ],
          language: lang,
        }).then((priceSets) => CRM.api4("PriceField", "get", {
          where: [["price_set_id", "=", priceSets[0]["price_set_id"]]],
          language: lang,
        })).then((priceFields) => {
          const priceFieldLabel = priceFields[0]['label'];

          return CRM.api4("PriceFieldValue", "get", {
            where: [["price_field_id", "=", priceFields[0]["id"]]],
            language: lang,
          }).then((priceFieldValues) => {
            if (priceFieldValues.length > 0) {
              const priceFieldAmount = priceFieldValues[0]['amount'];

              lineItems.push({
                description: `${event.title} - ${priceFieldLabel}`,
                amount: priceFieldAmount,
                // TODO add paper exam amount to charged total
                payableNow: (event['Exam_Details.Exam_Format'] === 'paper'),
              });
            } else {
              console.log('No price field found for event ID ' . event.id);
            }
            return Promise.resolve();
          });
        })
      )))
      .then(() => {
        // add fixed webform fee
        lineItems.push({
          amount: 135,
          description: 'Registration fee',
          payableNow: true
        });

        let totalAmount = 0;
        let amountPayable = 0;

        let examFeeHtml = [];

        examFeeHtml.push(`<table class="registration-fee-items" width="100%">`);

        examFeeHtml.push(`
          <tr>
            <th>Item</th>
            <th>Amount</th>
            <th>Payable now?</th>
          </tr>
        `);

        lineItems.forEach((line) => {
          examFeeHtml.push(`
            <tr class="event-fee">
              <td class="event-fee-title">${line.description}</td>
              <td class="event-fee-amount">${line.amount}</td>
              <td class="event-fee-payable">${line.payableNow ? '✔' : ''}</td>
            </tr>
          `);

          totalAmount += line.amount;
          amountPayable += line.payableNow ? line.amount : 0;
        });

        examFeeHtml.push(`
          <tr class="total-fee">
            <td class="event-fee-title"><b>Total fee</b></td>
            <td class="event-fee-amount">${totalAmount}</td>
          </tr>
        `);

        if (totalAmount !== amountPayable) {
          examFeeHtml.push(`
            <tr class="total-payable-now">
              <td class="event-fee-title"><em>Payable now</em></td>
              <td class="event-fee-amount">${amountPayable}</td>
            </tr>
          `);
        }

        examFeeHtml.push(`</table>`);

        $examFeeMarkup.append(examFeeHtml.join(''));

        $contributionAmountField.val(amountPayable);
      });
  }
});
