{crmScope extensionKey='advimport'}
<div class="advimport-upload-form-wrapper crm-block crm-form-block">
  {if $advimport_permission_import_contacts}
    <div class="crm-section" id="advimport-upload-file-section">
      <div class="label">{$form.uploadFile.label}</div>
      <div class="content">
        {$form.uploadFile.html}
        <div id="progress">
          <div class="progress-bar" style="width: 0%;"></div>
        </div>
      </div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.source.label}</div>
      <div class="content">{$form.source.html}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.group_or_tag.label}</div>
      <div class="content">
        {$form.group_or_tag.html}
         <div class="description">{ts}If selected, a group or tag will automatically be created, using the source name and the current date/time.{/ts}</div>
      </div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label"></div>
      <div class="content">
        <div class="crm-submit-buttons">
          {include file="CRM/common/formButtons.tpl" location="bottom"}
        </div>
      </div>
    </div>
    {if $active_queues_count}
      <p>{ts plural="There are currently %count import tasks running:" count=$active_queues_count}An import task is currently running:{/ts}</p>
      <table>
        <thead>
          <th>{ts}Queue{/ts}</th><th>{ts}Since{/ts}</th><th>{ts}Items left{/ts}</th><th>{ts}Actions{/ts}</th>
        </thead>
        <tbody>
          {foreach from=$active_queues item=q}
            <tr>
              <td>{$q.queue_name}</td>
              <td>{$q.submit_time}</td>
              <td>{$q.items}</td>
              <td>
                <a href="{crmURL p='civicrm/advimport/runner' q="reset=1&qrid=`$q.queue_name`"}" title="{ts output='js'}Run now{/ts}"><i class="fa fa-2x fa-play"></i></a>
                <a href="{crmURL p='civicrm/advimport' q="reset=1&flush=`$q.queue_name`"}" title="{ts output='js'}Cancel{/ts}" style="padding-left: 1em;"><i class="fa fa-2x fa-fire-extinguisher"></i></a>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    {else}
      <div class="crm-section">
        <div class="label"></div>
        <div class="content">
          <p>{ts}The import queue is empty. If there are tasks already running, they will be shown on this screen.{/ts}</p>
        </div>
      </div>
    {/if}
  {/if}

  {if $recent_imports}
    <h3>{ts}Recent imports{/ts}</h3>

    <table class="table table-striped crm-advimport-history">
      <thead>
        <th>{ts}ID{/ts}</th>
        <th>{ts}Author{/ts}</th>
        <th>{ts}Start Date{/ts}</th>
        <th>{ts}End Date{/ts}</th>
        <th>{ts}File Name{/ts}</th>
        <th>{ts}Items{/ts}</th>
        <th>{ts}Success{/ts}</th>
        <th>{ts}Warnings{/ts}</th>
        <th>{ts}Errors{/ts}</th>
      </thead>
      <tbody>
        {foreach from=$recent_imports item=i}
          <tr>
            <td>{$i.id}</td>
            <td>{$i.contact_display_name}</td>
            <td>{$i.start_date}</td>
            <td>{$i.end_date}</td>
            <td>{$i.filename}</td>
            <td>{if $i.table_name}<a href="{crmURL p="civicrm/a/#/advimport/`$i.id`"}">{$i.total_count}</a>{else}{$i.total_count}{/if}</td>
            <td>{if $i.table_name}<a href="{crmURL p="civicrm/a/#/advimport/`$i.id`/1"}">{$i.success_count}</a>{else}{$i.success_count}{/if}</td>
            <td>
              {if $i.table_name && $i.warning_count}
                <a href="{crmURL p="civicrm/a/#/advimport/`$i.id`/3"}">{$i.warning_count}</a>
              {else}
                {$i.warning_count}
              {/if}
            </td>
            <td>
              {if $i.table_name && $i.error_count}
                <a href="{crmURL p="civicrm/a/#/advimport/`$i.id`/2"}">{$i.error_count}</a>
                &nbsp;&nbsp; {* FIXME: css? *}
                <a href="{crmURL p="civicrm/advimport" q="reset=1&aid=`$i.id`"}" title="{ts escape='js'}Re-import errors{/ts}"><i class="fa fa-repeat" aria-hidden="true"></i></a>
              {else}
                {$i.error_count}
              {/if}
            </td>
            <td>
              {if $i.table_name}<a href="{crmURL p="civicrm/advimport" q="reset=1&aid=`$i.id`&replay_type=2"}" title="{ts escape='js'}Re-import everything{/ts}"><i class="fa fa-refresh" aria-hidden="true"></i></a>{/if}
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {/if}
</div>
{/crmScope}
