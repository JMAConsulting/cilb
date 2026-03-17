{crmScope extensionKey='countyCode'}
{if $action eq 8}
  {* Are you sure to delete form *}
  <div class="crm-block crm-form-block">
    <div class="crm-section">{ts}Are you sure you wish to delete this zip-code?{/ts}</div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
{else}
  <div class="crm-block crm-form-block">

    {foreach from=$elements item=element}
      <div class="crm-section">
        <div class="label">{$form.$element.label}</div>
        <div class="content">
        {$form.$element.html}
        </div>
        <div class="clear"></div>
      </div>
    {/foreach}

    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

  </div>

{/if}
{/crmScope}

