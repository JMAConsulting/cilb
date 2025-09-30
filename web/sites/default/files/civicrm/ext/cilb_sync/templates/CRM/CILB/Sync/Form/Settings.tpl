
<div class="help">
    {ts}Use these settings to configure sFTP connections to download files that will be used by Job.SyncExamFiles.{/ts}
</div>


<div class="crm-block crm-form-block crm-map-form-block">
    <h2>PearsonVUE Score Files</h2>
    <table class="form-layout-compressed">
      {foreach from=$pearson_fields key="setting_name" item="fieldSpec"}
        <tr class="crm-setting-form-block-{$setting_name}">
          <td class="label">
            {$form.$setting_name.label}
          </td>
          <td>{$form.$setting_name.html}</td>
        </tr>
      {/foreach}
    </table>
    <h2>CILB Entity Files</h2>
    <table class="form-layout-compressed">
      {foreach from=$cilb_fields key="setting_name" item="fieldSpec"}
        <tr class="crm-setting-form-block-{$setting_name}">
          <td class="label">{$form.$setting_name.label}</td>
          <td>{$form.$setting_name.html}</td>
        </tr>
      {/foreach}
    </table>
    <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>