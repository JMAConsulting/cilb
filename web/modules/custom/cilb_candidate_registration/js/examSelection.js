jQuery(document).ready(function ($) {
  // Check if form is in English or Spanish
  var lang;
  const currentUrl = window.location.href;

  // Check if '/es/' is in the URL
  if (currentUrl.indexOf("/es/") !== -1) {
    lang = "es_MX";
  } else {
    lang = "en_US";
  }

  const $examCatField = $('[data-drupal-selector="edit-select-exam-category"]');
  const $examPartField = $('[data-drupal-selector="edit-select-exam-parts"]');

  if (
    $examPartField &&
    $examCatField
  ) {

    $examCatField.on("change", function () {
    $examPartField.val("");

    const selectedCat = $(this).val();

    if (!selectedCat) {
      return;
    }

    CRM.api4("Event", "get", {
      select: ["id", "Exam_Details.Exam_Part"],
      join: [
        [
          "OptionValue AS option_value",
          "LEFT",
          ["event_type_id", "=", "option_value.value"],
        ],
      ],
      where: [
        ["option_value.option_group_id:name", "=", "event_type"],
        ["option_value.id", "=", selectedCat],
      ],
    }).then(
      function (events) {
        const eventParts = {};

        events.forEach((event) => eventParts[event.id] = event['Exam_Details.Exam_Part']);

        $examPartField.find("option").each(function () {
          var optionValue = $(this).val();

          if (optionValue === "") {
            return;
          }

          // Check if the optionValue is in the keys of eventParts
          if (parseInt(optionValue) in eventParts) {
            // TODO load label for exam part
            $(this).text()
            $(this).show(); // Show valid options
          } else {
            $(this).hide(); // Hide invalid options
          }
        });
      },
      function (failure) {
        console.log(failure);
      });
    });
    $examCatField.trigger("change");
  }
});
