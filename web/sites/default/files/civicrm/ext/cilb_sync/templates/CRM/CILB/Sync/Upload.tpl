<div class="crm-section" id="advimport-event-id-section">
  <div class="label">{$form.event_id.label}</div>
  <div class="content">{$form.event_id.html}</div>
  <div class="clear"></div>
</div>
{/literal}
<script type="text/javascript">
  (function($) {
    $(documetion).ready(function() {
      $('#advimport-event-id-section').insertAfter($('#advimport-upload-file-section'));
    });
  }(CRM.$));
</script>
{literal}