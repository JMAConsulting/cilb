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

  const ssnField = $("#edit-civicrm-1-contact-1-cg1-custom-5");
  const dobField = $("#edit-civicrm-1-contact-1-contact-birth-date");

  [ssnField, dobField].forEach((field) => {
    if (isExistingContact && field.length && field.val()) {
      field.prop("readonly", true);
      field.parent().addClass("form-readonly webform-readonly");
    }
    else if (!isExistingContact) {
      // "request info change" links dont apply if not logged in => remove
      field.parent().find('[href$="/request-information-change"]').remove();
    }
  });

  // replace final form submit button with a loading indicator on click
  $('.webform-button--submit').on('click', function () {
    $(this).hide();
    $(this).parent().append($('<div class="loader" style="max-width: 2rem; max-height: 2rem; margin: 0.5rem;" />'));
  });

});
