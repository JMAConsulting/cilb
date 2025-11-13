{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
<h3>
    {ts}A repeating set will be created with the following dates.{/ts}
</h3>
<table class="display row-highlight">
  <thead><tr>
    <th>#</th>
    <th>{ts}Start date{/ts}</th>
      {if $endDates}<th>{ts}End date{/ts}</th>{/if}
  </tr><thead>
  <tbody>
  {capture assign=count}0{/capture}
  {foreach from=$dates item="row" key="count"}
    <tr class="{cycle values="odd-row,even-row"}">
      <td>{$count+1}</td>
      <td>{$row.start_date}</td>
        {if $endDates}<td>{$row.end_date}</td>{/if}
    </tr>
  {/foreach}
  </tbody>
</table>
