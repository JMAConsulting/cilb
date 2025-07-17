<table class="crm-price-field-form-block-participant_visibility" style="display:none">
  <tr class="crm-price-field-form-block-participant_visibility">
    <td class="label">{$form.participant_visibility.label}</td>
    <td>{$form.participant_visibility.html}</td>
  </tr>
</table>
<script>
  CRM.$('tr.crm-price-field-form-block-participant_visibility').insertAfter(CRM.$('tr.crm-price-field-form-block-visibility_id'));
</script>
