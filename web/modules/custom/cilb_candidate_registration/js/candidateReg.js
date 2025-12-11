/**
 * Conditional rendering of on behalf of field and setting readonly for DOB and SIN fields
 */
jQuery(document).ready(function ($) {
  var next = $('#edit-actions-wizard-next input[type="submit"]');
  var behalfOf = $("#behalf-of");
  var candidateRep = $("#edit-candidate-representative-name");
  var candidateRepField = $(".form-item-candidate-representative-name");
  var returnPrev = $("#return-prev");
  var isCandidate = $("input[name='candidate_representative']");
  var cancelReg = $("#cancel-reg");
  var existingContactField = $("input[name='civicrm_1_contact_1_contact_existing']");
  // Move button to before on behalf of field.
  // TODO: why not have this done in the webform interface
  $('button[value="I am the Candidate"]').insertBefore('#behalf-of');
  $('button[value="Yo soy el candidato"]').insertBefore('#behalf-of');
  $('<br><br>').insertBefore('#behalf-of');

  // Check if form is in English or Spanish based on url
  const currentUrl = window.location.href;
  const lang = (currentUrl.indexOf("/es/") !== -1) ? 'es_MX' : 'en_US';

 // If registration is being completed on behalf of candidate
  // show and hide various fields and set isCandidate
  if (behalfOf.length) {
    candidateRepField.hide();
    behalfOf.on("click", function () {
    $('button[value="I am the Candidate"]').text('I affirm this is my own full name');
    $('button[value="Yo soy el candidato"]').text('Afirmo que este es mi nombre completo');
      behalfOf.hide();
      cancelReg.hide();

      // Unhide the representative name field for completing the registration on behalf of the candidate.
      candidateRepField.show();
      isCandidate.val(1);

      var originalhref = returnPrev.attr("href");
      returnPrev.removeAttr("href");

      var originalNextLabel = next.val();
      next.val((lang === 'es_MX') ? "Siguiente > " : "Next >");
      returnPrev.off("click").on("click", function (event) {
        $('button[value="I am the Candidate"]').text('I am the Candidate');
        $('button[value="Yo soy el candidato"]').text('Yo soy el candidato');
        event.preventDefault();
        returnPrev.attr("href", originalhref);
        behalfOf.show();
        cancelReg.show();
        next.parent().insertBefore(behalfOf);

        candidateRepField.hide();
        candidateRep.val("");
        isCandidate.val(0);
        next.val(originalNextLabel);
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

  const nameSuffix = $("input[name='civicrm_1_contact_1_contact_suffix_id']");
  const birthDate = $("input[name='civicrm_1_contact_1_contact_birth_date']");
  const ssn = $("input[name='civicrm_1_contact_1_cg1_custom_5']");

  const candidateContactInfoFields = $("fieldset#edit-candidate-contact-information input");

  setReadonly();

  if (isExistingContact()) {
    if ($('#edit-civicrm-1-contact-1-email-email').val() == '') {
      CRM.api4('Email', 'get', {
        where: [["location_type_id:name", "=", "Work"], ["contact_id", "=", $('#edit-civicrm-1-contact-1-contact-existing').val()]]
      }).then(function(batch) {
        if (batch.length > 0) {
          $('#edit-civicrm-1-contact-1-email-email').val(batch[0]['email']);
        }
      }, function(failure) {
        // handle failure
        console.log("API Call Failed:", failure);
      });
    }
  }
  // Clear DOB, birth date, name suffix when the user selects a new contact
  existingContactField.on("change", function () {
    if (isExistingContact()) {
      if ($('#edit-civicrm-1-contact-1-email-email').val() == '') {
        CRM.api4('Email', 'get', {
          where: [["location_type_id:name", "=", "Work"], ["contact_id", "=", $(this).val()]]
        }).then(function(batch) {
          if (batch.length > 0) {
            $('#edit-civicrm-1-contact-1-email-email').val(batch[0]['email']);
          }
        }, function(failure) {
          // handle failure
          console.log("API Call Failed:", failure);
        });
        return;
      }
    }
    var fieldsToReset = candidateContactInfoFields.add(nameSuffix).add(nameSuffix).add(birthDate).add(ssn);
    fieldsToReset.each(function (index) {
      console.log($(this));
      $(this).val("");
    });

  });
  // Replace final form submit button with a loading indicator on click
  $('.webform-button--submit').on('click', function () {
    $(this).hide();
    $(this).parent().append($('<div class="loader" style="max-width: 2rem; max-height: 2rem; margin: 0.5rem;" />'));
  });

  /**
   * Checks if there is an existing contact
   * @return true if there is an existing contact, otherwise false
   */
  function isExistingContact() {
    var contactId = $(
      "input[name='civicrm_1_contact_1_contact_existing']"
    ).val();
    // contactId is not numeric if no existing contact was found
    return !Number.isNaN(parseInt(contactId));
  }

  /**
    * Set the readonly attribute for SSN and Birth Date fields based on if we have an existing contact
    */
  function setReadonly() {
    var contactExists = isExistingContact();
    [ssn, birthDate].forEach((field) => {
      if (contactExists && field.length && field.val()) {
        field.prop("readonly", true);
        field.parent().addClass("form-readonly webform-readonly");
      }
      else if (!contactExists) {
        field.prop("readonly", false);
        field.parent().removeClass("form-readonly webform-readonly");
      }
    });
  }
});

