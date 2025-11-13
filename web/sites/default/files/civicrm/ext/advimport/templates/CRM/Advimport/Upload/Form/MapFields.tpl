{crmScope extensionKey='advimport'}
<div class="advimport-upload-form-wrapper crm-block crm-form-block">
  <div class="accordion ui-accordion ui-widget ui-helper-reset">
    <div class="crm-accordion-wrapper crm-ajax-accordion crm-advimport-upload-accordion">
      <div class="crm-accordion-header">{ts}Data import: map fields{/ts}</div>
      <div class="crm-accordion-body">
        {if $activity_subject}
          <h3>{$activity_subject}</h3>
        {/if}

        {if !empty($error)}
          <div class="messages help">
            <p><i class="fa fa-times-circle fa-2x"></i> {$error}</p>
          </div>
        {elseif $is_popup}
          {* Intentionally not using a div because of shoreditch theming *}
          <p>{ts}Are you sure you want to continue?{/ts} {ts}This will import all rows in this import, unless they have already been processed successfully.{/ts}</p>
        {elseif $mapfield_method == 'skip'}
          <div class="messages help">
            <p><i class="fa fa-check-circle fa-2x"></i> {ts}The data looks OK, you can continue to the next step.{/ts}</p>
          </div>
        {else}
          <div class="messages help">
            <p>{ts}Please check if the uploaded fields match the destination fields:{/ts}</p>
          </div>
        {/if}
        {if !empty($mapfield_instructions)}
          <div class="messages help">
            <p>{$mapfield_instructions}</p>
          </div>
        {/if}

        {foreach from=$elementNames item=f}
          <div class="crm-section">
            <div class="label">{$form.$f.label}</div>
            <div class="content">
              {$form.$f.html}
              {assign var='fieldName' value=$form.$f.value}
            </div>
            <div class="clear"></div>
          </div>
        {/foreach}

        <div class="crm-submit-buttons">
          {include file="CRM/common/formButtons.tpl" location="bottom"}
        </div>
      </div>
    </div>
  </div>
</div>
{/crmScope}
