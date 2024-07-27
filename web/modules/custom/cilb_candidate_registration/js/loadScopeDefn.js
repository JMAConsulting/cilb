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
});
