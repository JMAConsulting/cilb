{*Move the Transaction ID header*}
<script type="text/javascript">
  var examHeader = cj("table.selector > thead th").eq(1);
  cj("table.selector > thead th").first().insertAfter(examHeader);
</script>
{*Add a column for transaction ID for each record*}
<script type="text/javascript">
{foreach from=$rows item=row}
  cj("#rowid{$row.participant_id} > .crm-participant-event_title").after('<td class="crm-participant-trxn_id">' + "{$row.trxn_id}" + '</td>');
{/foreach}
</script>
