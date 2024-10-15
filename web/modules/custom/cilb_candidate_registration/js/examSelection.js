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

  const $examCatSelector = $('[data-drupal-selector="edit-select-exam-category"]');
  const $examPartSelector = $('[data-drupal-selector="edit-select-exam-parts"]');
  const $examCatIdField = $('[data-drupal-selector="edit-exam-category-id"]');
  const $eventIdsField = $('[data-drupal-selector="edit-event-ids"]');
  $examCatIdField.parent().hide();
  $eventIdsField.parent().hide();

  if ($examCatSelector.length) {
    // store exam cat id selection for reference on subsequent pages
    $examCatSelector.on('change', function () {
      $examCatIdField.val($examCatSelector.val());
    });
  }


  if ($examPartSelector.length) {
    // store event id selection for reference on subsequent pages
    $examPartSelector.on('change', function () {
      $eventIdsField.val($examPartSelector.val());
    });

    // hide all part options initially
    $examPartSelector.find("option").each(function () {
      $(this).hide();
    });

    $examPartSelector.val("");

    const selectedCat = $examCatIdField.val();

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
    }).then((events) => {
      const eventParts = {};

      events.forEach((event) => eventParts[event.id] = event['Exam_Details.Exam_Part']);
      let examOptions = [];
      $examPartSelector.find("option").each(function () {
        const optionValue = $(this).val();

        if (optionValue === "") {
            return;
        }

        // Check if the optionValue is in the keys of eventParts
        if (parseInt(optionValue) in eventParts) {
          // Show valid options
          // TODO strip the category part from event name display
          // $(this).text()
          examOptions.push({
            text: $(this).text(),
            id: optionValue,
          });
          $(this).show();
        } else {
          // Hide invalid
          $(this).hide();
        }
      });
      $examPartSelector.empty().select2({data: examOptions, multiple: true, width: '100%'});
    }, (failure) => {
        console.log(failure);
    });
  }
});
