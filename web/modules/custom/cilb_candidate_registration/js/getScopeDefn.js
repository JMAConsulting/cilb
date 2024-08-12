jQuery(document).ready(function ($) {
  if ($('input[name="select_your_exam_category"]')) {
    $('input[name="select_your_exam_category"]').change(function () {
      // Get selected exam type
      var selectedValue = $(
        'input[name="select_your_exam_category"]:checked'
      ).val();

      $("#edit-civicrm-1-participant-1-participant-event-id").val(
        selectedValue
      );
    });

    $('input[name="select_your_exam_category"]:checked').trigger("change");
  }
});
