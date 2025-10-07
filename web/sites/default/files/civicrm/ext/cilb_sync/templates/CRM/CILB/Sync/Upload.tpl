<div class="crm-section" id="advimport-event-id-section">
  <div class="label">{$form.event_id.label}</div>
  <div class="content">{$form.event_id.html}</div>
  <div class="clear"></div>
</div>
{literal}
<script type="text/javascript">
  (function($) {
    $(document).ready(function() {
      $('#advimport-event-id-section').insertAfter($('#advimport-upload-file-section'));
      var label = $('label[for="event_id"]').html();
      $('label[for="event_id"]').html(label + '<span class="crm-marker" title="This field is required.">*</span>');
      var source = $('#source').val();
      if (source.length < 1 || source != CRM.vars.cilb_sync.paper_source) {
        $('#advimport-event-id-section').hide();
      }
      $('#source').on('change', function() {
        if ($(this).val() != CRM.vars.cilb_sync.paper_source) {
          $('#event_id').val('0').trigger('change');
          $('#advimport-event-id-section').hide();
        }
        else {
          $('#advimport-event-id-section').show();
        }
      });
    });
  }(CRM.$));
</script>
{/literal}
