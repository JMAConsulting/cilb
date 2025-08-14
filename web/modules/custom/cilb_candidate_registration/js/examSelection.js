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

  const eventIdCategoryMapField = document.querySelector('[data-drupal-selector="edit-event-ids"]');
  const examCatField = document.querySelector('[data-drupal-selector="edit-select-exam-category"]');
  const examPartField = document.querySelector('[data-drupal-selector="edit-select-exam-parts"]');
  const plumbingPartField = document.querySelector('[data-drupal-selector="edit-exam-preference"]');
  const eventSelectionFieldset = document.querySelector('[data-drupal-selector="edit-select-exam"]');

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
    if (!eventIdCategoryMapField) {
      throw new Error('Missing exam category map');
    }

    // build event id => category map from the select element
    const eventIdCategoryMap = {};
    eventIdCategoryMapField.querySelectorAll('option').forEach((option) => {
      const eventId = parseInt(option.value);
      const catId = parseInt(option.innerText);
      eventIdCategoryMap[eventId] = catId;
    });

    // stash the list of all event options
    const allEventOptions = Array.from(examPartField.querySelectorAll('option'))
      .map((option) => ({
        id: parseInt(option.value),
        text: option.innerText,
        category: eventIdCategoryMap[option.value],
      }));

    const plumbingOption = Array.from(examCatField.querySelectorAll('option')).find((option) => option.innerText === 'Plumbing')
    const plumbingCatId = plumbingOption ? parseInt(plumbingOption.value) : null;

    /**
      * Loads the available exam parts depending on the selected category
      */
    const updateExamOptions = () => {
      const toggleFieldset = (on) => {
        eventSelectionFieldset.style.display = on ? 'block' : 'none';
      }
      const toggleInput = (input, on) => {
        input.parentElement.style.display = on ? 'block' : 'none';
        input.toggleAttribute('required', on);
        if (!on) {
          input.value = null;
        }
      }
      const examCatId = parseInt(examCatField.value);

      // if no exam category is selected, hide this input and its container
      if (!examCatId) {
        toggleFieldset(false);
        return;
      }

      // examCatId = 20 is plumbing
      // TODO: fetch this better
      if (plumbingPartField && examCatId === plumbingCatId) {
        // show examPref selector rather than examPart
        toggleInput(plumbingPartField, true);
        toggleInput(examPartField, false);
        return;
      }

      if (plumbingPartField) {
        toggleInput(plumbingPartField, false);
      }
      toggleInput(examPartField, true);

      const eventsForCat = allEventOptions.filter((option) => option.category === examCatId);

      // preserve current selection
      const currentlySelected = parseInt(examPartField.value);
      eventsForCat.forEach((option, i) => {
        if (option.id === currentlySelected) {
          eventsForCat[i].selected = true;
        }
      });

      if (eventsForCat.length) {

        // Remove the notice that there are no exam parts available
        examPartField.parentElement.querySelectorAll('em').forEach((e) => e.remove());

        // remove existing options and reinitialise select2 with new ones
        examPartField.querySelectorAll('option').forEach((e) => e.remove());
        $(examPartField).select2({ data: eventsForCat, multiple: true, width: '100%' });

        // unhide the input itself, replace with a notice
        examPartField.style.display = 'block';
      }
      else {
        // hide the input itself, replace with a notice

        examPartField.style.display = 'none';
        // Remove any notice that there are no exam parts available (to avoid dupes)
        examPartField.parentElement.querySelectorAll('em').forEach((e) => e.remove());

        // Add notice that no parts left
        const notice = document.createElement('em');
        notice.innerText = 'No more exam parts are available for this contractor type';
        examPartField.parentElement.append(notice);
      }

      toggleFieldset(true);
    };

    // run initial filter
    updateExamOptions();

    // update exam parts selector when category selector changes
    examCatField.addEventListener('change', () => {
      updateExamOptions();
    });

  }

});
