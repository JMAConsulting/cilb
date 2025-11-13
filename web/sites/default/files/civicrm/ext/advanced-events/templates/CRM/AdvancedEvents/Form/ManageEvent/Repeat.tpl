{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
<div class="help">{ts}Create events from this template{/ts}</div>
<div class="crm-block crm-form-block crm-event-manage-repeat-form-block">
  {include file="CRM/AdvancedEvents/Form/ManageEvent/RecurringEntity.tpl" recurringFormIsEmbedded=false}
</div>
<div>
  {if $rowsEmpty|| $rows}
  <div class="crm-block crm-content-block">
    {if $rowsEmpty}
      <div class="crm-results-block crm-results-block-empty">
        {include file="CRM/AdvancedEvents/Form/Search/EmptyResults.tpl"}
      </div>
    {/if}

    {if $rows}
      <div class="crm-results-block">
        {* Search request has returned 1 or more matching rows. *}
        {* This section handles form elements for action task select and submit *}

        {* This section displays the rows along and includes the paging controls *}
        <div id='participantSearch' class="crm-event-search-results">
          {include file="CRM/AdvancedEvents/Form/Selector.tpl" context="Search"}
        </div>
        {* END Actions/Results section *}
      </div>
    {/if}

  </div>
  {/if}
</div>