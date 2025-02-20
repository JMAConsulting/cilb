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
  const $examPrefSelector = $('[data-drupal-selector="edit-exam-preference"]');
  const $examCatIdField = $('[data-drupal-selector="edit-exam-category-id"]');
  const $eventIdsField = $('[data-drupal-selector="edit-event-ids"]');
  // we have to listen to change events on both radio buttons, thanks jquery...
  const $candidateDegreeSelectors = $('.form-item-civicrm-1-contact-1-cg1-custom-2 input')
  const $candidateDegreeSelectorYes = $('[data-drupal-selector="edit-civicrm-1-contact-1-cg1-custom-2-1"]');
  const $candidateDegreeField = $('[data-drupal-selector="edit-candidate-has-degree"]');

  $examCatIdField.parent().hide();
  $eventIdsField.parent().hide();
  $candidateDegreeField.parent().hide();

  if ($candidateDegreeSelectorYes.length) {
    // store candidate degree selection for reference on subsequent pages
    // NOTE: selector will load candidate value from DB initially => ensure propagated
    $candidateDegreeField.val($candidateDegreeSelectorYes.is(':checked') ? 1 : 0);
    $candidateDegreeSelectors.on('change', function () {
      $candidateDegreeField.val($candidateDegreeSelectorYes.is(':checked') ? 1 : 0);
    });
  }

  if ($examCatSelector.length) {
    // store exam cat id selection for reference on subsequent pages
    $examCatSelector.on('change', function () {
      $examCatIdField.val($examCatSelector.val());
    });
  }

  if ($examPrefSelector.length) {
    // store event id selection for reference on subsequent pages
    $examPrefSelector.on('change', function () {
       $eventIdsField.val($examPrefSelector.val());
    });
  }

  if ($examPartSelector.length) {
    // store event id selection for reference on subsequent pages
    $examPartSelector.on('change', function () {
      $eventIdsField.val($examPartSelector.val());
    });

    // hide all part options initially and clear starting selection
    $examPartSelector.find("option").each(function () {
      $(this).hide();
    });
    $examPartSelector.val("");

    const selectedCat = $examCatIdField.val();

    const eventFetchParams = {
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
    };

    // if candidate has Construction Bacc then dont show Trade
    // Knowledge exams
    if (parseInt($candidateDegreeField.val())) {
      eventFetchParams.where.push(["Exam_Details.Exam_Part", "!=", "TK"])
    }

    CRM.api4("Event", "get", eventFetchParams)
    .then((eventsForCategory) => eventsForCategory.map((e) => e.id))
    .then((eventIdsForCategory) => {

      // extract the options that match the selected part
      // from the starting options
      let finalOptions = [];

      $examPartSelector.find("option").each(function () {
        const eventId = parseInt($(this).val());

        if (!eventId) {
          return;
        }

        // Check if the optionValue is in the keys of eventParts
        if (eventIdsForCategory.some((id) => id === eventId)) {
          // Show valid options
          // TODO strip the category part from event name display
          // $(this).text()
          finalOptions.push({
            text: $(this).text(),
            id: eventId,
          });
        }
      });

      if (finalOptions.length) {
        $examPartSelector.empty().select2({data: finalOptions, multiple: true, width: '100%'});
      } else {
	      $examPartSelector.parent().empty()
          .append($('<em>No more exam parts are available for this contractor type</em>'));
      }
    }, (failure) => {
        console.log(failure);
    });
  }
});
