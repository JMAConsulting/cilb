<div class="crm-block crm-content-block nicechangelog-block" data-cid="{$contactId}">

  <h3>{ts}Change Log{/ts}: {$displayName}</h3>

  <div class="nicechangelog-filters">
    <div class="nicechangelog-filter-group">
      <span class="nicechangelog-filter-label">{ts}Date{/ts}:</span>
      <select class="nicechangelog-range-filter crm-form-select">
        {foreach from=$datePresets key=slug item=label}
          <option value="{$slug}"{if $slug eq $selectedRange} selected="selected"{/if}>{$label}</option>
        {/foreach}
      </select>
      <span class="nicechangelog-custom-range"{if $selectedRange neq 'custom'} style="display: none;"{/if}>
        <input type="date" class="nicechangelog-from crm-form-text" value="{$customFrom}" />
        &ndash;
        <input type="date" class="nicechangelog-to crm-form-text" value="{$customTo}" />
        <button type="button" class="nicechangelog-apply crm-button">{ts}Apply{/ts}</button>
      </span>
    </div>
  </div>

  {if $rowCount == 0}
    <div class="messages status no-popup">
      <i class="crm-i fa-info-circle" aria-hidden="true"></i>
      {ts}No changes were logged for this contact in the selected date range.{/ts}
    </div>
  {else}

    <div class="nicechangelog-filters">
      <div class="nicechangelog-filter-group">
        <span class="nicechangelog-filter-label">{ts}Action{/ts}:</span>
        {foreach from=$actionOptions key=slug item=label}
          <label class="nicechangelog-checkbox">
            <input type="checkbox" class="nicechangelog-action-filter" value="{$slug}" checked="checked" /> {$label}
          </label>
        {/foreach}
      </div>
      <div class="nicechangelog-filter-group">
        <span class="nicechangelog-filter-label">{ts}Component{/ts}:</span>
        {foreach from=$componentOptions key=slug item=label}
          <label class="nicechangelog-checkbox">
            <input type="checkbox" class="nicechangelog-component-filter" value="{$slug}" checked="checked" /> {$label}
          </label>
        {/foreach}
      </div>
    </div>

    <table class="nicechangelog-table display">
      <thead>
        <tr>
          <th class="nicechangelog-col-toggle"></th>
          <th>{ts}Action{/ts}</th>
          <th>{ts}Component{/ts}</th>
          <th>{ts}When{/ts}</th>
          <th>{ts}What changed{/ts}</th>
          <th>{ts}Altered By{/ts}</th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$rows item=row name=rows}
          {assign var=hasDiffs value=$row.diffs|@count}
          <tr class="nicechangelog-row{if $hasDiffs} nicechangelog-has-diffs{/if}" data-action="{$row.action_slug}" data-component="{$row.component_slug}">
            <td class="nicechangelog-col-toggle">
              {if $hasDiffs}
                <a href="#" class="nicechangelog-toggle" title="{ts}Show what changed{/ts}">
                  <i class="crm-i fa-caret-right" aria-hidden="true"></i>
                </a>
              {/if}
            </td>
            <td>{$row.action}</td>
            <td>{$row.component}</td>
            <td>{$row.when}</td>
            <td class="nicechangelog-summary">
              {if $row.altered_contact}
                {if $row.altered_contact_id}<a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.altered_contact_id`"}">{$row.altered_contact}</a>{else}{$row.altered_contact}{/if}{if $row.bracket} <span class="nicechangelog-bracket">[{$row.bracket}]</span>{/if}<br />
              {/if}
              {if $hasDiffs}
                <span class="nicechangelog-summary-text">{$row.summary}</span>
              {else}
                <span class="nicechangelog-summary-text nicechangelog-muted">&mdash;</span>
              {/if}
            </td>
            <td>
              {if $row.altered_by}{if $row.altered_by_id}<a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.altered_by_id`"}">{$row.altered_by}</a>{else}{$row.altered_by}{/if}{/if}
            </td>
          </tr>
          {if $hasDiffs}
            <tr class="nicechangelog-detail" data-action="{$row.action_slug}" data-component="{$row.component_slug}" style="display: none;">
              <td></td>
              <td colspan="5">
                <table class="nicechangelog-detail-table">
                  <thead>
                    <tr>
                      <th>{ts}Field{/ts}</th>
                      <th>{ts}Changed From{/ts}</th>
                      <th>{ts}Changed To{/ts}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$row.diffs item=diff}
                      <tr>
                        <td class="nicechangelog-field">{$diff.field}</td>
                        <td class="nicechangelog-from">{if $diff.from neq ''}{$diff.from}{else}<span class="nicechangelog-muted">{ts}(empty){/ts}</span>{/if}</td>
                        <td class="nicechangelog-to">{if $diff.to neq ''}{$diff.to}{else}<span class="nicechangelog-muted">{ts}(empty){/ts}</span>{/if}</td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </td>
            </tr>
          {/if}
        {/foreach}
      </tbody>
    </table>

    <div class="nicechangelog-noresults messages status no-popup" style="display: none;">
      {ts}No changes match the selected filters.{/ts}
    </div>

  {/if}
</div>
