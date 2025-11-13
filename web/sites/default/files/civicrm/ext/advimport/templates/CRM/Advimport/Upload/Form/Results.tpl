{crmScope extensionKey='advimport'}
<div class="advimport-upload-form-wrapper crm-block crm-form-block">
  <div class="accordion ui-accordion ui-widget ui-helper-reset">
    <div class="crm-accordion-wrapper crm-ajax-accordion crm-advimport-upload-accordion">
      <div class="crm-accordion-header">{ts}Data import complete{/ts}</div>
      <div class="crm-accordion-body">
        <h3>{ts}Done{/ts}</h3>

        <div class="messages help">
          <p>{ts}Data import is complete.{/ts} <a href="{crmURL p='civicrm/advimport' q='reset=1'}">{ts}Click here to import more data.{/ts}</a></p>
        </div>

        {if $import_info_messages}
          <div class="messages help">{$import_info_messages}</div>
        {/if}

        <div class="crm-section">
          <div class="label">{ts}Total items:{/ts}</div>
          <div class="content">{$import_stats.total_count}</div>
          <div class="clear"></div>
        </div>

        <div class="crm-section">
          <div class="label">{ts}Success:{/ts}</div>
          <div class="content"><a href="{$import_view_success_url}">{$import_stats.success_count}</a></div>
          <div class="clear"></div>
        </div>

        <div class="crm-section">
          <div class="label">{ts}Warnings:{/ts}</div>
          <div class="content"><a href="{$import_view_warnings_url}">{$import_stats.warning_count}</a></div>
          <div class="clear"></div>
        </div>

        <div class="crm-section">
          <div class="label">{ts}Errors:{/ts}</div>
          <div class="content"><a href="{$import_view_errors_url}">{$import_stats.error_count}</a></div>
          <div class="clear"></div>
        </div>

        {if $group_or_tag eq 'tag'}
          <div class="crm-section">
            <div class="label">{ts 1=$group_or_tag_name}Added to tag %1{/ts}</div>
            <div class="content">{$group_or_tag_count}</div>
            <div class="clear"></div>
          </div>
        {elseif $group_or_tag eq 'group'}
          <div class="crm-section">
            <div class="label">{ts 1=$group_or_tag_name}Added to group %1{/ts}</div>
            <div class="content"><a href="{crmURL p='civicrm/group/search' q="force=1&context=smog&gid=`$group_or_tag_id`"}">{$group_or_tag_count}</a></div>
            <div class="clear"></div>
          </div>
        {/if}

        <div class="crm-submit-buttons">
          {include file="CRM/common/formButtons.tpl" location="bottom"}
        </div>
      </div>
    </div>
  </div>
</div>
{/crmScope}
