{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}

{* Display contribution/event fees as normal using a copy of the core template: *}
{include file='CRM/Price/Page/LineItemCore.tpl'}

{*
  If this contribution includes an Event Fee, add our custom
  Afform which shows which exam registration records (linked by CustomField on participant)
*}

{foreach from=$lineItem item=value key=priceset}
  {foreach from=$value item=line}
    {if $line.financial_type_id eq 4}
      <div class="clear"></div>
      {include
        file='afform/InlineAfform.tpl'
        block=['module' => 'afsearchCandidatesForPayment', 'directive' => 'afsearch-candidates-for-payment']
        afformOptions=['contribution_id' => $line.contribution_id]
      }
      {break}
    {/if}
  {/foreach}
{/foreach}