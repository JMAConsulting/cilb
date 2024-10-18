jQuery(document).ready(function ($) {
  var next = $('#edit-actions input[type="submit"]');
  var behalfOf = $("#behalf-of");
  var candidateRep = $("#edit-civicrm-1-contact-1-cg1-custom-7");
  var candidateRepField = $(".form-item-civicrm-1-contact-1-cg1-custom-7");
  var returnPrev = $("#return-prev");
  var isCandidate = $("input[name='candidate_representative']");
  var cancelReg = $("#cancel-reg");

  var isExistingContact = $(
    "input[name='civicrm_1_contact_1_contact_existing']"
  ).val();

  // If registration is being completed on behalf of candidate
  if (behalfOf.length) {
    candidateRepField.hide();
    behalfOf.on("click", function () {
      behalfOf.hide();
      cancelReg.hide();

      // Unhide the representative name field for completing the registration on behalf of the candidate.
      candidateRepField.show();
      isCandidate.val(1);

      var originalhref = returnPrev.attr("href");
      returnPrev.removeAttr("href");

      next.val("Next >");

      returnPrev.off("click").on("click", function (event) {
        event.preventDefault();
        returnPrev.attr("href", originalhref);

        behalfOf.show();
        cancelReg.show();
        next.parent().insertBefore(behalfOf);

        candidateRepField.hide();
        candidateRep.val("");
        isCandidate.val(0);
        next.val("I am the Candidate");
      });
    });

    // Move next button on first page
    if (next.length && behalfOf.length) {
      next.parent().insertBefore(behalfOf);
    }

    if (isCandidate.val() == 1) {
      behalfOf.trigger("click");
    }
  }

  if (
    $("#edit-civicrm-1-contact-1-contact-birth-date").length &&
    isExistingContact
  ) {
    $("#edit-civicrm-1-contact-1-contact-birth-date").prop("readonly", true);

    // Add the 'webform-readonly' class to the parent div
    $(".js-form-item-civicrm-1-contact-1-contact-birth-date").addClass(
      "webform-readonly"
    );
    $(".js-form-item-civicrm-1-contact-1-contact-birth-date").addClass(
      "form-readonly"
    );
  }
});
