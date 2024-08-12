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
    });

    $('input[name="select_your_exam_category"]:checked').trigger("change");
  }
});
