(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.cilbExamSelection = {
    attach: function (context, settings) {
      if (!settings.cilbEventOptions) {
        return;
      }
      const eventOptions = Object.values(settings.cilbEventOptions);
      const bfEventTypes = Object.values(settings.cilbBFEventMap);

      // Check if form is in English or Spanish
      const currentUrl = window.location.href;
      // Check if '/es/' is in the URL
      const lang = (currentUrl.indexOf("/es/") !== -1) ? "es_MX" : "en_US";

      const categorySelector = document.querySelector('[data-drupal-selector="edit-select-category-id"]');
      const eventSelectFieldset = document.querySelector('[data-drupal-selector="edit-select-exam"]');
      const eventsSelector = document.querySelector('[data-drupal-selector="edit-select-exam-ids"]');
      const locationFieldset = document.querySelector('#edit-location-detail');
      const locationPlaceholder = document.querySelector('#location_detail');

      /**
       * On backend form, we need to filter the events selector based on the category selector
       */
      if (categorySelector && eventsSelector) {
        /**
          * Filter event selector by category
          */
        const updateExamOptions = () => {
          const selectedCategoryId = parseInt(categorySelector.value);

          // if no exam category is selected, hide the event selector
          if (!selectedCategoryId) {
            eventSelectFieldset.style.display = 'none';
            return;
          }

          const eventsForCat = eventOptions.filter((option) => (option.event_type_id === selectedCategoryId || option.event_type_id === bfEventTypes[selectedCategoryId]));

          // preserve current selection (getting multivalue from select2)
          const currentlySelected = $(eventsSelector).val().map((v) => parseInt(v));

          const selectOptions = eventsForCat.map((option) => ({
            id: option.id,
            text: option.label,
            selected: (currentlySelected.includes(option.id))
          }));

          // remove existing options and reinitialise select2 with new ones
          eventsSelector.querySelectorAll('option').forEach((e) => e.remove());
          $(eventsSelector).select2({ data: selectOptions, multiple: true, width: '100%' });

          // Always remove any prior notices to avoid duplication
          eventSelectFieldset.querySelectorAll('em').forEach((e) => e.remove());

          if (selectOptions.length) {
            // unhide the input
            eventsSelector.parentElement.style.display = 'block';
          }
          else {
            // hide the input and add a notice
            eventsSelector.parentElement.style.display = 'none';

            const notice = document.createElement('em');
            notice.innerText = 'No more exam parts are available for this contractor type';
            eventSelectFieldset.append(notice);
          }

          eventSelectFieldset.style.display = 'block';
        };

        // run initial filter
        updateExamOptions();

        // update exam parts selector when category selector changes
        categorySelector.addEventListener('change', () => {
          updateExamOptions();
        });

      }

      /**
       * Show address detail for events selected that have address
       */
      if (eventsSelector && locationFieldset && locationPlaceholder) {
        const updateLocationDetail = () => {

          // get selection (getting multivalue from select2)
          const selectedEvents = $(eventsSelector).val().map((v) => parseInt(v));

          if (!selectedEvents.length) {
            locationFieldset.style.display = 'none';
          }

          const addresses = eventOptions.filter((event) => selectedEvents.includes(event.id)).map((event) => event.address).filter((address) => address);

          if (!addresses.length) {
            locationFieldset.style.display = 'none';
            return;
          }

          locationPlaceholder.innerHTML = addresses.join('<br />')
          locationFieldset.style.display = 'block';
        }
        updateLocationDetail();

        // listen to select2 changes
        $(eventsSelector).on('change', () => updateLocationDetail());
      }

    }

  }

})(jQuery, Drupal, drupalSettings);


