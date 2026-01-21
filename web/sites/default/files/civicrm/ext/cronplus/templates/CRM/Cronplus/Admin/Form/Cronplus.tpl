<div id="cronplus_controls">
  <div id="cronplus_control_cron" style="display: inline; padding-right:10px">
    {$form.cron.html}
  </div>
  <div class="description" id="cronplus_control_cron_text" style="display: inline; padding-right:10px"></div>
  <div class="description" style="display: block; padding:0px 0px 10px 0px">
<pre>*  *  *  *  *
-  -  -  -  -
|  |  |  |  |
|  |  |  |  +----- day of week (0 - 7) (Sunday=0 or 7)
|  |  |  +---------- month (1 - 12)
|  |  +--------------- day of month (1 - 31)
|  +-------------------- hour (0 - 23)
+------------------------- min (0 - 59)

* any value
, value list separator
- range of values
</pre>
  </div>
</div>


<script type="text/javascript">
  CRM.$(function(){ldelim}
    var tr = `
    <tr id="tr-crm-job-form-block-scheduled" class="crm-job-form-block-scheduled">
      <td class="label">
        {$form.cron.label}
        {help id="id-cronplus" file="CRM/Cronplus/Admin/Form/Cronplus.hlp"}
      </td>
      <td id="cronplus_placeholder"></td>
    </tr>`;
    CRM.$(tr).insertAfter('.crm-job-form-block-run_frequency');
    CRM.$('#cronplus_controls').appendTo(CRM.$('#cronplus_placeholder'));
    setCronText();

    {literal}
    CRM.$('#run_frequency').change(function(){
      setCron(CRM.$('#run_frequency').val());
    });

    CRM.$('#cron').change(function(){
      setCronText();
    });

    function setCron(value){
      switch(value){
      case "Daily":
          CRM.$('#cron').val('0 0 * * *');
          break;
      case "Hourly":
          CRM.$('#cron').val('0 * * * *');
          break;
      case "Weekly":
          CRM.$('#cron').val('0 0 * * 0');
          break;
      case "Monthly":
          CRM.$('#cron').val('0 0 1 * *');
          break;
      case "Yearly":
          CRM.$('#cron').val('0 0 1 1 *');
          break;
      case "Quarter":
          CRM.$('#cron').val('0 0 1 */3 *');
          break;
      case "Always":
          CRM.$('#cron').val('* * * * *');
          break;
      }

      setCronText();
    }

    function setCronText(){
      cronText = prettyCron.toString(CRM.$('#cron').val());
      cronText = cronText.replace("Every minute", "Always");
      CRM.$('#cronplus_control_cron_text').text(cronText);
    }
    {/literal}

  {rdelim});
</script>
