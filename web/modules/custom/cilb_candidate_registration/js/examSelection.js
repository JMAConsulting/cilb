/**
 * Storing of inputted values for subsequent pages and conditional loading of exam parts
 */
jQuery(document).ready(function($) {
  // Check if form is in English or Spanish
  var lang;
  const currentUrl = window.location.href;

  // Check if '/es/' is in the URL
  if (currentUrl.indexOf("/es/") !== -1) {
    lang = "es_MX";
  } else {
    lang = "en_US";
  }

  const examCatField = document.querySelector('[data-drupal-selector="edit-select-exam-category"]');
  const examPartField = document.querySelector('[data-drupal-selector="edit-select-exam-parts"]');
  const examPrefField = document.querySelector('[data-drupal-selector="edit-exam-preference"]');
  // we have to listen to change events on both radio buttons, thanks jquery...
  const candidateDegreeSelectors = $('.form-item-civicrm-1-contact-1-cg1-custom-2 input')
  const candidateDegreeSelectorYes = $('[data-drupal-selector="edit-civicrm-1-contact-1-cg1-custom-2-1"]');
  const candidateDegreeField = $('[data-drupal-selector="edit-candidate-has-degree"]');

  candidateDegreeField.parent().hide();

  if (candidateDegreeSelectorYes.length) {
    // store candidate degree selection for reference on subsequent pages
    // NOTE: selector will load candidate value from DB initially => ensure propagated
    candidateDegreeField.val(candidateDegreeSelectorYes.is(':checked') ? 1 : 0);
    candidateDegreeSelectors.on('change', function() {
      candidateDegreeField.val(candidateDegreeSelectorYes.is(':checked') ? 1 : 0);
    });
  }

  if (examCatField && examPartField) {
    const startingEvents = Array.from(examPartField.querySelectorAll('option'))
      .map((option) => ({
        id: option.value,
        text: option.innerText,
      }));

    // run initial filter
    filterExamPartsByCategory();

    // update exam parts selector when category selector changes
    examCatField.addEventListener('change', () => {
      // deselect existing exam part
      examPartField.value = null;
      filterExamPartsByCategory();
    });

  }

  /**
    * Loads the available exam parts depending on the selected category
    */
  const filterExamPartsByCategory = () => {
    const examCatId = examCatField.value;

    // if no exam category is selected, hide this input and its container
    if (!examCatId) {
      examPartField.parentElement.style.display = 'none'
      return;
    }

    const eventFetchParams = {
      select: ["id"],
      join: [
        [
          "OptionValue AS option_value",
          "LEFT",
          ["event_type_id", "=", "option_value.value"],
        ],
      ],
      where: [
        ["option_value.option_group_id:name", "=", "event_type"],
        ["option_value.id", "=", examCatId],
      ],
    };

    CRM.api4("Event", "get", eventFetchParams)
      .then((events) => events.map((e) => e.id))
      .then((eventIds) => {
        let optionsLeft = false;
        examPartField.querySelectorAll('option').forEach((option) => {
          const show = eventIds.includes(option.value);
          option.style.display = show ? 'block' : 'none';
          if (show) {
            // if any option is showing
            optionsLeft = true;
          }
        });

        return optionsLeft;
      })
      .then((optionsLeft) => {
        if (optionsLeft) {
          // examPartSelector.select2({ data: finalOptions, multiple: true, width: '100%' });
          examPartField.parentElement.style.display = 'block';
          // Remove the notice that there are no exam parts available
          examPartField.parentElement.querySelectorAll('em').forEach((e) => e.remove());
        }
        else {
          examPartField.parentElement.style.display = 'block';
          // Remove the notice that there are no exam parts available
          examPartField.parentElement.querySelectorAll('em').forEach((e) => e.remove());
          const notice = document.createElement('em');
          notice.innerText = 'No more exam parts are available for this contractor type';
          examPartField.parentElement.append(notice);
        }
      });
  }
});
