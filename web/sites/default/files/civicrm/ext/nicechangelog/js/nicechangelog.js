(function($) {
  'use strict';

  // Expand / collapse a change row's inline diff detail.
  function toggleRow($row) {
    if (!$row.hasClass('nicechangelog-has-diffs')) {
      return;
    }
    var $detail = $row.next('.nicechangelog-detail');
    $row.toggleClass('nicechangelog-open');
    $detail.toggle($row.hasClass('nicechangelog-open'));
  }

  // Apply the action + component checkbox filters to a given block.
  function applyFilters($block) {
    var actions = $block.find('.nicechangelog-action-filter:checked').map(function() {
      return this.value;
    }).get();
    var components = $block.find('.nicechangelog-component-filter:checked').map(function() {
      return this.value;
    }).get();

    var visible = 0;
    $block.find('tr.nicechangelog-row').each(function() {
      var $row = $(this);
      var match = actions.indexOf($row.data('action') + '') !== -1 &&
        components.indexOf($row.data('component') + '') !== -1;
      $row.toggle(match);
      if (!match) {
        // Keep an expanded detail row in sync when its parent is hidden.
        $row.removeClass('nicechangelog-open');
        $row.next('.nicechangelog-detail').hide();
      }
      else if ($row.hasClass('nicechangelog-open')) {
        $row.next('.nicechangelog-detail').show();
      }
      if (match) {
        visible++;
      }
    });

    $block.find('.nicechangelog-noresults').toggle(visible === 0);
    $block.find('table.nicechangelog-table').toggle(visible !== 0);
  }

  // Reload the tab content with a new server-side date range.
  function reload($block) {
    var range = $block.find('.nicechangelog-range-filter').val();
    var params = {reset: 1, cid: $block.data('cid'), ncl_range: range};
    if (range === 'custom') {
      params.ncl_from = $block.find('.nicechangelog-from').val();
      params.ncl_to = $block.find('.nicechangelog-to').val();
    }
    var url = CRM.url('civicrm/nicechangelog/changelog', params);
    var $panel = $block.closest('.ui-tabs-panel');
    if ($panel.length && CRM.loadPage) {
      CRM.loadPage(url, {target: $panel});
    }
    else {
      window.location.href = url;
    }
  }

  function init(context) {
    $('.nicechangelog-block', context).each(function() {
      var $block = $(this);
      if ($block.data('nicechangelog-init')) {
        return;
      }
      $block.data('nicechangelog-init', true);

      $block.on('click', '.nicechangelog-row.nicechangelog-has-diffs', function(e) {
        // Don't toggle when following a contact link in the row.
        if ($(e.target).closest('a').length && !$(e.target).closest('.nicechangelog-toggle').length) {
          return;
        }
        e.preventDefault();
        toggleRow($(this));
      });

      $block.on('change', '.nicechangelog-action-filter, .nicechangelog-component-filter', function() {
        applyFilters($block);
      });

      $block.on('change', '.nicechangelog-range-filter', function() {
        var isCustom = $(this).val() === 'custom';
        $block.find('.nicechangelog-custom-range').toggle(isCustom);
        if (!isCustom) {
          reload($block);
        }
      });

      $block.on('click', '.nicechangelog-apply', function(e) {
        e.preventDefault();
        reload($block);
      });
    });
  }

  $(function() {
    init(document);
  });

  $(document).on('crmLoad', function(e) {
    init(e.target);
  });

})(CRM.$ || cj || jQuery);
