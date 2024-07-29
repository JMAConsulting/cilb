jQuery(document).ready(function ($) {
  if ($('input[name="select_your_exam_category"]')) {
    $('input[name="select_your_exam_category"]').change(function () {
      // Get selected exam type
      var selectedValue = $(
        'input[name="select_your_exam_category"]:checked'
      ).val();

      
      var checkedRadioId = $(
        "input[name='select_your_exam_category']:checked"
      ).attr("id");
      var selectedExamName = $("label[for='" + checkedRadioId + "']")
        .text()
        .trim();
      localStorage.setItem("exam_choice", selectedExamName);

      $("#edit-civicrm-1-participant-1-participant-event-id").val(
        selectedValue
      );

      getEventDescription(selectedValue);
    });

    // Get event description
    function getEventDescription(eventId) {
      var apiUrl = "/civicrm/ajax/rest?entity=Event&action=get&json=1";

      $.ajax({
        url: apiUrl,
        type: "POST",
        data: {
          entity: "Event",
          action: "get",
          json: 1,
          id: eventId,
          select: ["description"],
        },
        success: function (data) {
          if (data.is_error === 0 && data.values && data.values[eventId]) {
            var description = data.values[eventId].description;
            localStorage.setItem("temp_markup", description);
          } else {
            console.log("Error or no description found in the response");
          }
        },
        error: function (xhr, status, error) {
          console.log("AJAX error: " + error);
        },
      });
    }

    $('input[name="select_your_exam_category"]:checked').trigger("change");
  }
});
