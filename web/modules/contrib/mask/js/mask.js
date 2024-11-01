(function ($, Drupal, drupalSettings, once) {

  // Disables masking by data-mask attribute.
  $.jMaskGlobals.dataMask = false;

  // Use modules settings for digit translation.
  $.jMaskGlobals.translation = {};
  for (var symbol in drupalSettings.mask.translation) {
    var options = drupalSettings.mask.translation[symbol];
    options.pattern = new RegExp(options.pattern);
    $.jMaskGlobals.translation[symbol] = options;
  }

  Drupal.behaviors.mask = {
    attach(context, settings) {
      // Get mask fields and apply mask.
      $(once('mask', '[data-mask-value]', context)).each(maskElements);
    }
  };

  // Mask elements.
  function maskElements() {
    const mask_element = $(this);

    // Gets mask options.
    const maskValue = mask_element.attr('data-mask-value');
    const maskOptions = {
      reverse: mask_element.attr('data-mask-reverse') === 'true',
      clearIfNotMatch: mask_element.attr('data-mask-clearifnotmatch') === 'true',
      selectOnFocus: mask_element.attr('data-mask-selectonfocus') === 'true',
      translation: drupalSettings.mask.translation
    };

    // Applies the mask.
    mask_element.mask(maskValue, maskOptions);
  }
})(jQuery, Drupal, drupalSettings,  once);
