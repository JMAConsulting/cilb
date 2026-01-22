{* header *}
{crmScope extensionKey='cilb_exam_registration'}
<div class="crm-block crm-form-block crm-participant-form-block">
  <h3 class="title">{ts}Transfer Exam Registration{/ts}</h3>
  
  <table class="form-layout-compressed">
    <tr class="crm-participant-form-block-new_event_id">
      <td class="label">{$form.new_event_id.label}</td>
      <td>{$form.new_event_id.html}
      </td>
    </tr>
  </table>
  
  <fieldset>
    <legend>{ts}Current Registration{/ts}</legend>
    <div class="crm-summary-row">
      <div class="crm-label">{ts}Current Exam{/ts}</div>
      <div class="crm-content">
        {if $old_event_title}
          {$old_event_title} (ID: {$old_event_id})
        {else}
          {ts}Exam not found{/ts}
        {/if}
      </div>
    </div>
    <div class="crm-summary-row">
      <div class="crm-label">{ts}Participant{/ts}</div>
      <div class="crm-content">
        ID: {$participant_id} {if $contact_name}({$contact_name}){/if}
      </div>
    </div>
  </fieldset>
  
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
{/crmScope}

