jQuery(document).ready(function ($) {
  $(document).ready(function () {
    var $button = $('#edit-actions input[type="submit"]');
    var $link = $("#return-prev");

    // Move next button on first page
    if ($button.length && $link.length) {
      $button.parent().insertBefore($link);
    }
  });
});
