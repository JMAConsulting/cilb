jQuery(document).ready(function ($) {
  $(document).ready(function () {
    var $button = $('#edit-actions input[type="submit"]');
    var $link = $("#someone_else");

    // Move next button on first page
    if ($button.length && $link.length) {
      $button.parent().insertBefore($link);
    }

    var $editActions = $("#edit-actions");
    var $someoneElse = $("#someone_else");
    var $returnPrev = $("#return-prev");
    var $isCandidate = $("#edit-are-you-candidate");
    var $notCandidate = $("#edit-not-candidate");

    if ($notCandidate) {
      $notCandidate.hide();
    }

    // Hide buttons and "next" button if user is not candidate
    $someoneElse.on("click", function (event) {
      event.preventDefault();

      // Hide buttons
      $editActions.hide();
      $someoneElse.hide();
      $isCandidate.hide();
      $notCandidate.show();

      // Make previous button re-add elements
      $returnPrev.removeAttr("href");

      $returnPrev.off("click").on("click", function (event) {
        event.preventDefault();

        // Show buttons again
        $editActions.show();
        $someoneElse.show();
        $isCandidate.show();
        $notCandidate.hide();

        // Set href back to original registration form
        $returnPrev.attr("href", "https://cilb.jmaconsulting.biz/register");
      });
    });
  });
});
